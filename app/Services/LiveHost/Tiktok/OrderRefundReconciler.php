<?php

declare(strict_types=1);

namespace App\Services\LiveHost\Tiktok;

use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\ProductOrder;
use App\Models\TiktokReportImport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderRefundReconciler
{
    /**
     * Scan tiktok_shop ProductOrders within the import's period that are
     * refunded or cancelled and matched to a live session, then record an
     * auto-approved negative LiveSessionGmvAdjustment for each.
     *
     * Trusts the pre-tagged `matched_live_session_id` (populated by the
     * sync hook in TikTokOrderSyncService and the backfill command). The
     * matcher uses "most recently started session wins" semantics; this
     * is looser than the strict-no-multi-match policy of the legacy xlsx
     * path, but acceptable here.
     *
     * Policy (2026-07): refund/cancel adjustments are auto-approved so they
     * deduct from the session's Net GMV immediately, without PIC sign-off.
     * Sessions inside a locked payroll period are skipped — those numbers are
     * frozen and must not drift.
     *
     * @return array{applied_count: int, skipped_count: int}
     */
    public function reconcile(TiktokReportImport $import): array
    {
        $periodStart = $import->period_start->copy()->startOfDay();
        $periodEnd = $import->period_end->copy()->endOfDay();

        $orders = ProductOrder::query()
            ->where('source', 'tiktok_shop')
            ->when($import->platform_account_id, function ($query) use ($import) {
                $query->where('platform_account_id', $import->platform_account_id);
            })
            ->whereNotNull('matched_live_session_id')
            ->whereIn('status', ['refunded', 'cancelled', 'returned'])
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('paid_time', [$periodStart, $periodEnd])
                    ->orWhereBetween('cancelled_at', [$periodStart, $periodEnd]);
            })
            ->get();

        return $this->applyForOrders($orders);
    }

    /**
     * Apply auto-approved refund deductions for a collection of ProductOrders,
     * recomputing each affected session's cached gmv_adjustment. Idempotent —
     * an order that already has an auto-adjustment (any status) is left alone.
     *
     * @param  Collection<int, ProductOrder>  $orders
     * @return array{applied_count: int, skipped_count: int}
     */
    public function applyForOrders($orders): array
    {
        $applied = 0;
        $skipped = 0;
        $touchedSessionIds = [];

        foreach ($orders as $order) {
            if ($this->alreadyRecorded($order)) {
                $skipped++;

                continue;
            }

            $session = LiveSession::find($order->matched_live_session_id);

            if ($session === null || $session->isInLockedPayrollPeriod()) {
                $skipped++;

                continue;
            }

            $refundAmount = $this->refundAmountFor($order);

            if ($refundAmount <= 0.0) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($order, $refundAmount) {
                LiveSessionGmvAdjustment::create([
                    'live_session_id' => $order->matched_live_session_id,
                    'amount_myr' => -1 * $refundAmount,
                    'reason' => "Auto: Order #{$this->orderRef($order)} refunded/cancelled (RM {$refundAmount})",
                    'status' => 'approved',
                    'adjusted_by' => null,
                    'adjusted_at' => now(),
                ]);
            });

            $touchedSessionIds[$order->matched_live_session_id] = $session;
            $applied++;
        }

        foreach ($touchedSessionIds as $session) {
            $session->recalcCachedGmvAdjustment();
        }

        return [
            'applied_count' => $applied,
            'skipped_count' => $skipped,
        ];
    }

    /**
     * Refund amount: prefer the sum of refunded payment amounts when present;
     * fall back to total_amount for cancelled/refunded/returned orders.
     */
    private function refundAmountFor(ProductOrder $order): float
    {
        $refundedFromPayments = (float) $order->payments()
            ->where('status', 'refunded')
            ->sum('amount');

        if ($refundedFromPayments > 0) {
            return $refundedFromPayments;
        }

        if (in_array($order->status, ['cancelled', 'refunded', 'returned'], true)) {
            return (float) $order->total_amount;
        }

        return 0.0;
    }

    private function orderRef(ProductOrder $order): string
    {
        return $order->platform_order_id ?? $order->order_number;
    }

    /**
     * Idempotency guard — don't create a duplicate adjustment if a prior
     * reconciler run already recorded one for this order (any status).
     */
    private function alreadyRecorded(ProductOrder $order): bool
    {
        return LiveSessionGmvAdjustment::query()
            ->where('reason', 'like', "Auto: Order #{$this->orderRef($order)} %")
            ->exists();
    }
}
