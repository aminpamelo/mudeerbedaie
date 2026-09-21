<?php

namespace App\Jobs;

use App\Models\ProductOrder;
use App\Services\SettingsService;
use App\Services\Shipping\EasyParcelTrackingSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * One-shot backfill that reconciles every in-flight EasyParcel shipment with its
 * latest courier status. This is the "Sync EasyParcel" button's worker — the same
 * per-order logic the 30-minute poller runs ({@see SyncEasyParcelTracking}), but
 * unbounded and in the background so an admin can flip a large backlog of stuck
 * "shipped" orders to delivered in one click without blocking the request.
 */
class BackfillEasyParcelTracking implements ShouldQueue
{
    use Queueable;

    /** Give the whole backlog room to finish — each order costs one courier API call. */
    public int $timeout = 3600;

    public function handle(SettingsService $settings, EasyParcelTrackingSync $sync): void
    {
        if (! $settings->isEasyParcelConnected()) {
            Log::warning('EasyParcel backfill skipped — account not connected.');

            return;
        }

        $checked = 0;
        $delivered = 0;

        // Pass 1 — pull the latest status for in-flight shipments.
        ProductOrder::query()
            ->where('shipping_provider', 'easyparcel')
            ->whereNotNull('tracking_id')
            ->whereNull('delivered_at')
            ->whereNotIn('status', ['delivered', 'cancelled', 'returned', 'refunded'])
            ->orderBy('id')
            ->chunkById(50, function ($orders) use ($sync, &$checked, &$delivered): void {
                foreach ($orders as $order) {
                    try {
                        $resolved = $sync->syncOrder($order);
                        $checked++;

                        if ($resolved === 'delivered') {
                            $delivered++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('EasyParcel backfill: order sync failed', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // Pass 2 — re-verify orders the tracking sync marked delivered and revert
        // any that the courier has not actually delivered (guards against a stale
        // "Delivered" placeholder step having flipped an in-transit parcel). Limited
        // to sync-flipped orders whose stored code is not a real delivered(5)/
        // returned(6) to keep the live re-check cheap.
        $reverted = 0;

        ProductOrder::query()
            ->where('shipping_provider', 'easyparcel')
            ->where('status', 'delivered')
            ->whereNotNull('tracking_id')
            ->whereHas('notes', fn ($n) => $n->where('type', 'system')
                ->where('message', 'like', 'Auto-marked as delivered from EasyParcel tracking%'))
            ->orderBy('id')
            ->chunkById(50, function ($orders) use ($sync, &$reverted): void {
                foreach ($orders as $order) {
                    if (in_array((int) data_get($order->metadata, 'easyparcel_status_code'), [5, 6], true)) {
                        continue;
                    }

                    try {
                        if ($sync->repairFalseDelivered($order) === 'reverted') {
                            $reverted++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('EasyParcel backfill: repair check failed', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('EasyParcel backfill complete', [
            'checked' => $checked,
            'newly_delivered' => $delivered,
            'reverted_false_delivered' => $reverted,
        ]);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('easyparcel-backfill'))->dontRelease()];
    }
}
