<?php

declare(strict_types=1);

namespace App\Services\Upsell;

use App\Models\FunnelOrder;
use App\Models\UpsellCommissionPayout;
use App\Models\UpsellCommissionPayoutSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Shared query for paid upsell funnel orders.
 *
 * Single source of truth used by every Phase B upsell report
 * (dashboard by-teacher, dashboard by-product, teacher upsell tab).
 *
 * Filters honoured:
 *  - Class session has a non-null `upsell_funnel_ids` (i.e. an upsell was assigned).
 *  - Class session `session_date` between the optional from/to inclusive.
 *  - Optional: session's `upsell_funnel_ids` contains the given funnel id.
 *  - Optional: session's `upsell_pic_user_ids` contains the given user id.
 *  - Linked `product_order.payment_status` is `paid`.
 *  - Linked `product_order.status` is NOT `returned`, `cancelled`, or `refunded`
 *    (defence in depth — payment_status is flipped by markAsReturned/
 *    markAsCancelled, but legacy rows or out-of-band updates can leave
 *    payment_status='paid' on a returned order).
 */
class UpsellPaidOrdersQuery
{
    /**
     * Order status values that disqualify a paid order from counting toward
     * upsell revenue/commission.
     */
    public const EXCLUDED_ORDER_STATUSES = ['returned', 'cancelled', 'refunded'];

    private ?string $from = null;

    private ?string $to = null;

    private ?int $funnelId = null;

    private ?int $picId = null;

    public function forDateRange(?string $from, ?string $to): self
    {
        $this->from = $from ?: null;
        $this->to = $to ?: null;

        return $this;
    }

    public function forFunnelId(?int $funnelId): self
    {
        $this->funnelId = $funnelId ?: null;

        return $this;
    }

    public function forPicId(?int $picId): self
    {
        $this->picId = $picId ?: null;

        return $this;
    }

    /**
     * The single source of truth. Every aggregate method funnels through this.
     */
    public function baseQuery(): Builder
    {
        return FunnelOrder::query()
            ->whereHas('productOrder', fn ($q) => $q
                ->where('payment_status', 'paid')
                ->whereNotIn('status', self::EXCLUDED_ORDER_STATUSES))
            ->whereHas('classSession', function ($q) {
                $q->whereNotNull('upsell_funnel_ids');

                if ($this->from) {
                    $q->whereDate('session_date', '>=', $this->from);
                }

                if ($this->to) {
                    $q->whereDate('session_date', '<=', $this->to);
                }

                if ($this->funnelId) {
                    $q->whereJsonContains('upsell_funnel_ids', $this->funnelId);
                }

                if ($this->picId) {
                    $q->whereJsonContains('upsell_pic_user_ids', $this->picId);
                }
            });
    }

    /**
     * Eager-loaded collection of paid funnel orders.
     */
    public function get(): Collection
    {
        return $this->baseQuery()
            ->with(['classSession', 'productOrder.items'])
            ->get();
    }

    /**
     * Aggregate paid revenue + commission by upsell teacher.
     *
     * Sessions may list multiple upsell teachers in `upsell_teacher_ids` (JSON array).
     * When a session has N teachers the per-order revenue and commission are split
     * equally across each teacher. Sessions with no upsell teachers are skipped.
     *
     * @return SupportCollection<int, array{
     *     teacher_id: int,
     *     teacher_name: string|null,
     *     sessions_count: int,
     *     paid_orders: int,
     *     paid_revenue: float,
     *     commission_earned: float,
     *     commission_paid: float,
     *     commission_pending: float,
     *     top_products: SupportCollection<int, array{product_id: int|null, product_name: string, units: int, revenue: float}>,
     * }>
     */
    public function byTeacher(): SupportCollection
    {
        $orders = $this->baseQuery()
            ->with(['classSession', 'productOrder.items'])
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
            $orderRevenue = (float) $order->funnel_revenue;
            $rate = (float) ($session->upsell_teacher_commission_rate ?? 0);
            $orderCommission = $orderRevenue * ($rate / 100);

            $teacherRevenueShare = $orderRevenue / $split;
            $teacherCommissionShare = $orderCommission / $split;

            foreach ($teacherIds as $teacherId) {
                if (! isset($byTeacher[$teacherId])) {
                    $byTeacher[$teacherId] = [
                        'teacher_id' => (int) $teacherId,
                        'session_ids' => [],
                        'paid_orders' => 0,
                        'paid_revenue' => 0.0,
                        'commission_earned' => 0.0,
                        'product_lines' => [],
                    ];
                }

                $byTeacher[$teacherId]['session_ids'][$session->id] = true;
                $byTeacher[$teacherId]['paid_orders']++;
                $byTeacher[$teacherId]['paid_revenue'] += $teacherRevenueShare;
                $byTeacher[$teacherId]['commission_earned'] += $teacherCommissionShare;

                foreach ($order->productOrder?->items ?? [] as $item) {
                    $pid = $item->product_id;
                    if (! $pid) {
                        continue;
                    }

                    if (! isset($byTeacher[$teacherId]['product_lines'][$pid])) {
                        $byTeacher[$teacherId]['product_lines'][$pid] = [
                            'product_id' => $pid,
                            'product_name' => $item->product_name ?? 'Unknown',
                            'units' => 0,
                            'revenue' => 0.0,
                        ];
                    }

                    $byTeacher[$teacherId]['product_lines'][$pid]['units'] += (int) $item->quantity_ordered;
                    $byTeacher[$teacherId]['product_lines'][$pid]['revenue'] += ((float) $item->total_price) / $split;
                }
            }
        }

        if (empty($byTeacher)) {
            return collect();
        }

        $users = User::whereIn('id', array_keys($byTeacher))->get()->keyBy('id');
        $paidByTeacher = $this->paidCommissionByTeacher(array_keys($byTeacher));

        return collect($byTeacher)
            ->map(function (array $row) use ($users, $paidByTeacher): array {
                $user = $users->get($row['teacher_id']);

                $topProducts = collect($row['product_lines'])
                    ->sortByDesc('revenue')
                    ->take(5)
                    ->values();

                $earned = round($row['commission_earned'], 2);
                $paid = round((float) ($paidByTeacher[$row['teacher_id']] ?? 0), 2);
                $pending = round(max(0.0, $earned - $paid), 2);

                return [
                    'teacher_id' => $row['teacher_id'],
                    'teacher_name' => $user?->name,
                    'sessions_count' => count($row['session_ids']),
                    'paid_orders' => $row['paid_orders'],
                    'paid_revenue' => round($row['paid_revenue'], 2),
                    'commission_earned' => $earned,
                    'commission_paid' => $paid,
                    'commission_pending' => $pending,
                    'top_products' => $topProducts,
                ];
            })
            ->sortByDesc('commission_earned')
            ->values();
    }

    /**
     * Sum of paid commission per teacher, scoped to the active date range.
     *
     * "Paid" here means: belongs to an UpsellCommissionPayout with
     * status='paid', for sessions whose `session_date` falls in the same
     * date range used by commission_earned. We deliberately filter on
     * session_date (not payout.paid_at) so that "paid" is the subset of
     * "earned" for the same reporting period.
     *
     * @param  array<int, int>  $teacherIds
     * @return array<int, float>
     */
    private function paidCommissionByTeacher(array $teacherIds): array
    {
        if (empty($teacherIds)) {
            return [];
        }

        $query = UpsellCommissionPayoutSession::query()
            ->select('upsell_commission_payouts.teacher_user_id')
            ->selectRaw('SUM(upsell_commission_payout_sessions.commission_amount) as paid_total')
            ->join('upsell_commission_payouts', 'upsell_commission_payouts.id', '=', 'upsell_commission_payout_sessions.upsell_commission_payout_id')
            ->join('class_sessions', 'class_sessions.id', '=', 'upsell_commission_payout_sessions.class_session_id')
            ->where('upsell_commission_payouts.status', UpsellCommissionPayout::STATUS_PAID)
            ->whereIn('upsell_commission_payouts.teacher_user_id', $teacherIds);

        if ($this->from) {
            $query->whereDate('class_sessions.session_date', '>=', $this->from);
        }

        if ($this->to) {
            $query->whereDate('class_sessions.session_date', '<=', $this->to);
        }

        return $query->groupBy('upsell_commission_payouts.teacher_user_id')
            ->pluck('paid_total', 'teacher_user_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Aggregate paid revenue + units by product, tagged with the funnel order
     * line type (`main`, `bump`, `upsell`, `downsell`) inferred from
     * `funnel_orders.order_type`.
     *
     * Note: `product_order_items` has no explicit line_type column. The line
     * type is derived from the parent FunnelOrder's `order_type`. In this app's
     * data model each FunnelOrder maps 1:1 to a ProductOrder, so items inherit
     * their parent funnel order's type cleanly.
     *
     * @return SupportCollection<int, array{
     *     product_id: int,
     *     product_name: string,
     *     line_type: string,
     *     funnel_ids: SupportCollection,
     *     units: int,
     *     revenue: float,
     * }>
     */
    public function byProduct(): SupportCollection
    {
        $orders = $this->baseQuery()
            ->with('productOrder.items')
            ->get();

        $lines = collect();

        foreach ($orders as $order) {
            $lineType = $order->order_type ?: 'main';

            foreach ($order->productOrder?->items ?? [] as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $lines->push([
                    'product_id' => (int) $item->product_id,
                    'product_name' => $item->product_name ?? 'Unknown',
                    'line_type' => $lineType,
                    'funnel_id' => $order->funnel_id,
                    'units' => (int) $item->quantity_ordered,
                    'revenue' => (float) $item->total_price,
                ]);
            }
        }

        return $lines
            ->groupBy('product_id')
            ->map(function (SupportCollection $rows, $productId): array {
                return [
                    'product_id' => (int) $productId,
                    'product_name' => $rows->first()['product_name'],
                    'line_type' => $rows->first()['line_type'],
                    'funnel_ids' => $rows->pluck('funnel_id')->filter()->unique()->values(),
                    'units' => (int) $rows->sum('units'),
                    'revenue' => round((float) $rows->sum('revenue'), 2),
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }
}
