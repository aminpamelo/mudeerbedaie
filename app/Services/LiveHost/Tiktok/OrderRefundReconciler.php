<?php

declare(strict_types=1);

namespace App\Services\LiveHost\Tiktok;

use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\TiktokOrder;
use App\Models\TiktokReportImport;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class OrderRefundReconciler
{
    /**
     * Tail window after a session's actual_end_at within which a freshly
     * created order is still considered attributable to that live. TikTok
     * often delays checkout confirmation by several hours, so we accept up
     * to 12h post-end.
     */
    private const TAIL_HOURS = 12;

    /**
     * Scan every `TiktokOrder` tied to the given `order_list` import and
     * propose a negative `LiveSessionGmvAdjustment` for each row that is
     * refunded or cancelled and can be unambiguously attributed to a single
     * LiveSession by time window.
     *
     * Proposed adjustments do NOT feed the session's cached
     * `gmv_adjustment` aggregate until a PIC approves them
     * (see LiveSessionGmvAdjustmentController::approve).
     *
     * @return array{proposed_count: int, skipped_count: int}
     */
    public function reconcile(TiktokReportImport $import): array
    {
        $proposed = 0;
        $skipped = 0;

        $orders = TiktokOrder::query()
            ->where('import_id', $import->id)
            ->where(function ($query) {
                $query->where('order_refund_amount_myr', '>', 0)
                    ->orWhereNotNull('cancelled_time');
            })
            ->get();

        foreach ($orders as $order) {
            if ($this->alreadyProposed($order)) {
                $skipped++;

                continue;
            }

            if ($order->created_time === null) {
                $skipped++;

                continue;
            }

            $refundAmount = $this->refundAmountFor($order);

            if ($refundAmount <= 0.0) {
                $skipped++;

                continue;
            }

            $session = $this->matchSessionByWindow($order->created_time, $import->platform_account_id);

            if ($session === null) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($order, $session, $refundAmount) {
                LiveSessionGmvAdjustment::create([
                    'live_session_id' => $session->id,
                    'amount_myr' => -1 * $refundAmount,
                    'reason' => "Auto: Order #{$order->tiktok_order_id} refunded/cancelled (RM {$refundAmount})",
                    'status' => 'proposed',
                    'adjusted_by' => null,
                    'adjusted_at' => now(),
                ]);

                $order->matched_live_session_id = $session->id;
                $order->save();
            });

            $proposed++;
        }

        return [
            'proposed_count' => $proposed,
            'skipped_count' => $skipped,
        ];
    }

    /**
     * Resolve the refund amount for an order. Prefer the explicit
     * `order_refund_amount_myr` column when populated; fall back to the full
     * `order_amount_myr` for cancelled orders where the platform may not have
     * recorded a refund figure.
     */
    private function refundAmountFor(TiktokOrder $order): float
    {
        $refund = (float) ($order->order_refund_amount_myr ?? 0);

        if ($refund > 0) {
            return $refund;
        }

        if ($order->cancelled_time !== null) {
            return (float) ($order->order_amount_myr ?? 0);
        }

        return 0.0;
    }

    /**
     * Find the unique LiveSession whose live-window overlaps the order's
     * created_time. Returns null if zero or more than one session matches;
     * multi-match requires PIC review.
     *
     * When $platformAccountId is provided (i.e. the parent import is pinned
     * to a specific shop), the candidate set is restricted to sessions from
     * that shop — so a refund on Shop A can't land on a parallel Shop B
     * live-window by accident.
     */
    private function matchSessionByWindow(CarbonInterface $createdAt, ?int $platformAccountId = null): ?LiveSession
    {
        $candidates = LiveSession::query()
            ->whereNotNull('actual_start_at')
            ->when($platformAccountId !== null, function ($query) use ($platformAccountId) {
                $query->where('platform_account_id', $platformAccountId);
            })
            ->where('actual_start_at', '<=', $createdAt)
            ->where(function ($query) use ($createdAt) {
                $query->whereNull('actual_end_at')
                    ->orWhereRaw(
                        $this->dateAddExpr(),
                        [self::TAIL_HOURS, $createdAt]
                    );
            })
            ->limit(2)
            ->get();

        if ($candidates->count() !== 1) {
            return null;
        }

        return $candidates->first();
    }

    /**
     * Build a driver-aware "actual_end_at + X hours >= ?" raw predicate so the
     * window check works on both MySQL and SQLite.
     */
    private function dateAddExpr(): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return 'DATE_ADD(actual_end_at, INTERVAL ? HOUR) >= ?';
        }

        return "datetime(actual_end_at, '+' || ? || ' hours') >= ?";
    }

    /**
     * Idempotency guard — don't create a duplicate proposal if a prior
     * reconciler run already recorded one for this order.
     */
    private function alreadyProposed(TiktokOrder $order): bool
    {
        return LiveSessionGmvAdjustment::query()
            ->where('reason', 'like', "Auto: Order #{$order->tiktok_order_id} %")
            ->exists();
    }
}
