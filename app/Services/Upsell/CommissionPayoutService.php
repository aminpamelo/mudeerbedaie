<?php

declare(strict_types=1);

namespace App\Services\Upsell;

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\UpsellCommissionPayout;
use App\Models\UpsellCommissionPayoutSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builds per-teacher commission payouts from paid upsell funnel orders.
 *
 * Mirrors the Phase B `UpsellPaidOrdersQuery::byTeacher()` splitting rules:
 * when a session lists N upsell teachers, the per-order commission is split
 * equally among them.
 */
class CommissionPayoutService
{
    /**
     * Preview per-teacher unpaid commission within a date window.
     *
     * Sessions that already appear in an existing payout (draft, locked, or
     * paid) are excluded to prevent double-payment.
     *
     * @return Collection<int, array{
     *     teacher_id: int,
     *     teacher_name: string|null,
     *     session_ids: Collection<int, int>,
     *     session_count: int,
     *     commission_total: float,
     * }>
     */
    public function preview(string $from, string $to): Collection
    {
        $alreadyCovered = UpsellCommissionPayoutSession::query()
            ->pluck('class_session_id')
            ->all();

        $orders = FunnelOrder::query()
            ->whereHas('productOrder', fn ($q) => $q->where('payment_status', 'paid'))
            ->whereHas('classSession', function ($q) use ($from, $to) {
                $q->whereNotNull('upsell_funnel_ids')
                    ->whereDate('session_date', '>=', $from)
                    ->whereDate('session_date', '<=', $to);
            })
            ->when(! empty($alreadyCovered), function ($q) use ($alreadyCovered) {
                $q->whereNotIn('class_session_id', $alreadyCovered);
            })
            ->with('classSession')
            ->get();

        $byTeacher = [];

        foreach ($orders as $order) {
            $session = $order->classSession;
            if (! $session) {
                continue;
            }

            $teacherIds = $session->upsell_teacher_ids ?? [];
            if (empty($teacherIds)) {
                continue;
            }

            $split = count($teacherIds);
            $rate = (float) ($session->upsell_teacher_commission_rate ?? 0);
            $orderCommission = ((float) $order->funnel_revenue) * ($rate / 100);
            $share = $orderCommission / $split;

            foreach ($teacherIds as $teacherId) {
                $teacherId = (int) $teacherId;

                if (! isset($byTeacher[$teacherId])) {
                    $byTeacher[$teacherId] = [
                        'teacher_id' => $teacherId,
                        'session_ids' => [],
                        'commission_total' => 0.0,
                    ];
                }

                $byTeacher[$teacherId]['session_ids'][$session->id] = true;
                $byTeacher[$teacherId]['commission_total'] += $share;
            }
        }

        if (empty($byTeacher)) {
            return collect();
        }

        $users = User::query()
            ->whereIn('id', array_keys($byTeacher))
            ->get()
            ->keyBy('id');

        return collect($byTeacher)
            ->map(function (array $row) use ($users): array {
                $sessionIds = collect(array_keys($row['session_ids']))->values();

                return [
                    'teacher_id' => $row['teacher_id'],
                    'teacher_name' => $users->get($row['teacher_id'])?->name,
                    'session_ids' => $sessionIds,
                    'session_count' => $sessionIds->count(),
                    'commission_total' => round($row['commission_total'], 2),
                ];
            })
            ->sortByDesc('commission_total')
            ->values();
    }

    /**
     * Create a draft payout for one teacher across the given session ids.
     *
     * @param  array<int>  $sessionIds
     */
    public function createPayout(int $teacherUserId, string $from, string $to, array $sessionIds): UpsellCommissionPayout
    {
        if (empty($sessionIds)) {
            throw new InvalidArgumentException('At least one session id is required to create a payout.');
        }

        return DB::transaction(function () use ($teacherUserId, $from, $to, $sessionIds) {
            $sessions = ClassSession::query()
                ->whereIn('id', $sessionIds)
                ->get()
                ->keyBy('id');

            $missing = collect($sessionIds)->diff($sessions->keys());
            if ($missing->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Unknown class session ids: '.$missing->implode(', ')
                );
            }

            $totalCommission = 0.0;
            $sessionRows = [];

            foreach ($sessions as $session) {
                $teacherIds = $session->upsell_teacher_ids ?? [];
                if (! in_array($teacherUserId, $teacherIds, false)) {
                    throw new InvalidArgumentException(
                        "Teacher {$teacherUserId} is not assigned to session {$session->id}."
                    );
                }

                $split = count($teacherIds);
                $rate = (float) ($session->upsell_teacher_commission_rate ?? 0);

                $paidRevenueForSession = (float) FunnelOrder::query()
                    ->where('class_session_id', $session->id)
                    ->whereHas('productOrder', fn ($q) => $q->where('payment_status', 'paid'))
                    ->sum('funnel_revenue');

                if ($paidRevenueForSession <= 0) {
                    throw new RuntimeException(
                        "Session {$session->id} has no paid funnel revenue to pay out."
                    );
                }

                $share = ($paidRevenueForSession * ($rate / 100)) / $split;
                $totalCommission += $share;

                $sessionRows[] = [
                    'class_session_id' => $session->id,
                    'paid_revenue' => round($paidRevenueForSession, 2),
                    'commission_rate' => round($rate, 2),
                    'commission_amount' => round($share, 2),
                ];
            }

            $payout = UpsellCommissionPayout::create([
                'teacher_user_id' => $teacherUserId,
                'period_start' => $from,
                'period_end' => $to,
                'total_commission' => round($totalCommission, 2),
                'session_count' => count($sessionRows),
                'status' => UpsellCommissionPayout::STATUS_DRAFT,
            ]);

            foreach ($sessionRows as $row) {
                $payout->sessions()->create($row);
            }

            return $payout->fresh('sessions');
        });
    }
}
