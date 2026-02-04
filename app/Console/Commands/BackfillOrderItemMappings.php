<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Services\TikTok\OrderItemLinker;
use Illuminate\Console\Command;

class BackfillOrderItemMappings extends Command
{
    protected $signature = 'tiktok:backfill-order-mappings
        {--account= : Filter by platform account ID}
        {--deduct-stock : Also deduct stock for shipped/delivered orders}';

    protected $description = 'Backfill unlinked order items with product/package mappings from PlatformSkuMapping';

    public function handle(OrderItemLinker $linker): int
    {
        $this->info('Starting order item backfill...');

        $query = ProductOrderItem::query()
            ->whereNull('product_id')
            ->whereNull('package_id')
            ->whereNotNull('platform_sku')
            ->whereHas('order', function ($q) {
                $q->whereNotNull('platform_account_id')
                    ->whereNotNull('platform_id');
            });

        if ($accountId = $this->option('account')) {
            $query->whereHas('order', function ($q) use ($accountId) {
                $q->where('platform_account_id', $accountId);
            });
        }

        $items = $query->with('order')->get();

        if ($items->isEmpty()) {
            $this->info('No unlinked order items found.');

            return self::SUCCESS;
        }

        $this->info("Found {$items->count()} unlinked order items.");

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        $linked = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $order = $item->order;

            if (! $order || ! $order->platform_id || ! $order->platform_account_id) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $wasLinked = $linker->linkItemToMapping($item, $order->platform_id, $order->platform_account_id);

            if ($wasLinked) {
                $linked++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Linked: {$linked} | Skipped: {$skipped}");

        // Stock deduction phase
        if ($this->option('deduct-stock') && $linked > 0) {
            $this->info('Deducting stock for shipped/delivered orders...');

            $orderIds = $items->filter(fn ($item) => $item->product_id || $item->package_id)
                ->pluck('order_id')
                ->unique();

            $orders = ProductOrder::whereIn('id', $orderIds)
                ->whereIn('status', ['shipped', 'delivered'])
                ->get();

            $totalDeducted = 0;

            foreach ($orders as $order) {
                $result = $linker->deductStockForOrder($order);
                $totalDeducted += $result['deducted'];
            }

            $this->info("Stock deducted for {$totalDeducted} item(s) across {$orders->count()} order(s).");
        }

        return self::SUCCESS;
    }
}
