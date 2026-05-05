<?php

declare(strict_types=1);

namespace App\Services\LiveHost\Tiktok;

use App\Models\LiveSessionGmvAdjustment;
use App\Models\ProductOrder;
use App\Models\TiktokReportImport;
use Illuminate\Support\Facades\DB;

class OrderRefundReconciler
{
    /**
     * Scan tiktok_shop ProductOrders within the import's period that are
     * refunded or cancelled and matched to a live session, then propose a
     * negative LiveSessionGmvAdjustment for each.
     *
     * Trusts the pre-tagged `matched_live_session_id` (populated by the
     * sync hook in TikTokOrderSyncService and the backfill command). The
     * matcher uses "most recently started session wins" semantics; this
     * is looser than the strict-no-multi-match policy of the legacy xlsx
     * path, but acceptable here because PIC must approve every proposal.
     *
     * Proposed adjustments do NOT feed the session's cached gmv_adjustment
     * aggregate until a PIC approves them
     * (see LiveSessionGmvAdjustmentController::approve).
     *
     * @return array{proposed_count: int, skipped_count: int}
     */
    public function reconcile(TiktokReportImport $import): array
    {
        $proposed = 0;
        $skipped = 0;

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

        foreach ($orders as $order) {
            if ($this->alreadyProposed($order)) {
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
                    'status' => 'proposed',
                    'adjusted_by' => null,
                    'adjusted_at' => now(),
                ]);
            });

            $proposed++;
        }

        return [
            'proposed_count' => $proposed,
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
     * Idempotency guard — don't create a duplicate proposal if a prior
     * reconciler run already recorded one for this order.
     */
    private function alreadyProposed(ProductOrder $order): bool
    {
        return LiveSessionGmvAdjustment::query()
            ->where('reason', 'like', "Auto: Order #{$this->orderRef($order)} %")
            ->exists();
    }
}
