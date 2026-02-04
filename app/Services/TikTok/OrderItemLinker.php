<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\Package;
use App\Models\PlatformSkuMapping;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Log;

class OrderItemLinker
{
    /**
     * Link an order item to an internal product/package using PlatformSkuMapping.
     */
    public function linkItemToMapping(ProductOrderItem $item, int $platformId, int $accountId): bool
    {
        if (! $item->platform_sku) {
            return false;
        }

        $mapping = PlatformSkuMapping::findMapping($platformId, $accountId, $item->platform_sku);

        if (! $mapping) {
            return false;
        }

        $updateData = [];

        if ($mapping->isPackageMapping()) {
            $updateData['package_id'] = $mapping->package_id;
            $updateData['product_id'] = null;
            $updateData['product_variant_id'] = null;
        } elseif ($mapping->isProductMapping()) {
            $updateData['product_id'] = $mapping->product_id;
            $updateData['product_variant_id'] = $mapping->product_variant_id;
            $updateData['package_id'] = null;
        } else {
            return false;
        }

        // Assign default warehouse if not already set
        if (! $item->warehouse_id) {
            $defaultWarehouse = Warehouse::getDefault();
            if ($defaultWarehouse) {
                $updateData['warehouse_id'] = $defaultWarehouse->id;
            }
        }

        $item->update($updateData);
        $mapping->markAsUsed();

        Log::info('[OrderItemLinker] Linked order item to mapping', [
            'item_id' => $item->id,
            'platform_sku' => $item->platform_sku,
            'product_id' => $updateData['product_id'] ?? null,
            'package_id' => $updateData['package_id'] ?? null,
        ]);

        return true;
    }

    /**
     * Deduct stock for all linked items in an order.
     *
     * @return array{deducted: int, skipped: int, errors: int}
     */
    public function deductStockForOrder(ProductOrder $order): array
    {
        $summary = ['deducted' => 0, 'skipped' => 0, 'errors' => 0];

        if (! in_array($order->status, ['shipped', 'delivered'])) {
            return $summary;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if (! $item->warehouse_id) {
                $summary['skipped']++;

                continue;
            }

            if ($item->package_id && ! $item->product_id) {
                $deducted = $this->deductStockForPackageItem($item, $order);
                $deducted ? $summary['deducted']++ : $summary['skipped']++;
            } elseif ($item->product_id) {
                $deducted = $this->deductStockForProductItem($item, $order);
                $deducted ? $summary['deducted']++ : $summary['skipped']++;
            } else {
                $summary['skipped']++;
            }
        }

        return $summary;
    }

    /**
     * Deduct stock for a single product item (with duplicate prevention).
     */
    public function deductStockForProductItem(ProductOrderItem $item, ProductOrder $order): bool
    {
        return $this->deductStockWithValidation(
            $item,
            $item->product_id,
            $item->product_variant_id,
            $item->warehouse_id,
            $item->quantity_ordered,
            $order,
            "Order item: {$item->product_name}"
        );
    }

    /**
     * Deduct stock for a package item by expanding to its component products.
     */
    public function deductStockForPackageItem(ProductOrderItem $item, ProductOrder $order): bool
    {
        $package = Package::with('products')->find($item->package_id);

        if (! $package || $package->products->isEmpty()) {
            Log::warning('[OrderItemLinker] Package has no products', [
                'item_id' => $item->id,
                'package_id' => $item->package_id,
            ]);

            return false;
        }

        $allDeducted = true;

        foreach ($package->products as $product) {
            $quantity = ($product->pivot->quantity ?? 1) * $item->quantity_ordered;
            $deducted = $this->deductStockWithValidation(
                $item,
                $product->id,
                $product->pivot->product_variant_id ?? null,
                $item->warehouse_id,
                $quantity,
                $order,
                "Package item: {$item->product_name} (Product: {$product->name})"
            );

            if (! $deducted) {
                $allDeducted = false;
            }
        }

        return $allDeducted;
    }

    /**
     * Deduct stock with per-item duplicate prevention.
     */
    private function deductStockWithValidation(
        ProductOrderItem $item,
        int $productId,
        ?int $variantId,
        int $warehouseId,
        float $quantity,
        ProductOrder $order,
        string $description
    ): bool {
        // Check for existing deduction
        $existing = StockMovement::where('reference_type', 'App\\Models\\ProductOrderItem')
            ->where('reference_id', $item->id)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', 'out')
            ->exists();

        if ($existing) {
            Log::debug('[OrderItemLinker] Stock already deducted for item', [
                'item_id' => $item->id,
                'product_id' => $productId,
            ]);

            return false;
        }

        // Find or create stock level
        $stockLevel = StockLevel::firstOrCreate([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
        ], [
            'quantity' => 0,
            'reserved_quantity' => 0,
            'available_quantity' => 0,
            'average_cost' => 0,
        ]);

        $quantityBefore = $stockLevel->quantity;
        $quantityAfter = $quantityBefore - $quantity;

        $stockLevel->update([
            'quantity' => $quantityAfter,
            'available_quantity' => $stockLevel->available_quantity - $quantity,
            'last_movement_at' => now(),
        ]);

        if ($quantityAfter < 0) {
            Log::warning('[OrderItemLinker] Stock is now NEGATIVE', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'product_id' => $productId,
                'quantity_after' => $quantityAfter,
            ]);
        }

        StockMovement::create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'type' => 'out',
            'quantity' => -$quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'unit_cost' => 0,
            'reference_type' => 'App\\Models\\ProductOrderItem',
            'reference_id' => $item->id,
            'notes' => "Stock deducted: Platform order (Order #{$order->order_number}, Item #{$item->id}) - {$description}"
                .($quantityAfter < 0 ? ' [WARNING: Stock is now NEGATIVE by '.abs($quantityAfter).' units]' : ''),
            'created_by' => auth()->id(),
        ]);

        Log::info('[OrderItemLinker] Stock deducted', [
            'order_id' => $order->id,
            'item_id' => $item->id,
            'product_id' => $productId,
            'quantity' => -$quantity,
            'quantity_after' => $quantityAfter,
        ]);

        return true;
    }
}
