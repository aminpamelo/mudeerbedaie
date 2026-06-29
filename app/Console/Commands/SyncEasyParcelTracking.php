<?php

namespace App\Console\Commands;

use App\Models\ProductOrder;
use App\Services\SettingsService;
use App\Services\Shipping\EasyParcelTrackingSync;
use Illuminate\Console\Command;

/**
 * Scheduled poller that reconciles in-flight EasyParcel shipments with their
 * latest courier status. Acts as the reliable fallback to the push webhook —
 * EasyParcel webhook coverage varies by plan, so polling guarantees orders
 * eventually flip to "delivered" even when no callback arrives.
 */
class SyncEasyParcelTracking extends Command
{
    protected $signature = 'easyparcel:sync-tracking {--limit=100 : Maximum number of orders to reconcile this run}';

    protected $description = 'Reconcile in-flight EasyParcel shipments with their latest courier tracking status';

    public function handle(SettingsService $settings, EasyParcelTrackingSync $sync): int
    {
        if (! $settings->isEasyParcelConnected()) {
            $this->warn('EasyParcel is not connected — skipping tracking sync.');

            return self::SUCCESS;
        }

        $orders = ProductOrder::query()
            ->where('shipping_provider', 'easyparcel')
            ->whereNotNull('tracking_id')
            ->whereNull('delivered_at')
            ->whereNotIn('status', ['delivered', 'cancelled', 'returned', 'refunded'])
            ->orderBy('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No in-flight EasyParcel shipments to reconcile.');

            return self::SUCCESS;
        }

        $delivered = 0;
        $checked = 0;

        foreach ($orders as $order) {
            $resolved = $sync->syncOrder($order);
            $checked++;

            if ($resolved === 'delivered') {
                $delivered++;
                $this->line("  ✓ Order {$order->order_number} marked delivered.");
            }
        }

        $this->info("Checked {$checked} shipment(s); {$delivered} newly delivered.");

        return self::SUCCESS;
    }
}
