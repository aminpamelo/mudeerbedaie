<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'itemable_type',
        'itemable_id',
        'package_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'product_name',
        'variant_name',
        'sku',
        'quantity_ordered',
        'quantity_shipped',
        'quantity_cancelled',
        'unit_price',
        'total_price',
        'unit_cost',
        'product_snapshot',
        'package_snapshot',
        'package_items_snapshot',
        // Platform-specific fields
        'platform_sku',
        'platform_product_name',
        'platform_variation_name',
        'platform_category',
        'platform_discount',
        'seller_discount',
        'unit_original_price',
        'subtotal_before_discount',
        'returned_quantity',
        'quantity_affected',
        'item_weight_kg',
        'fulfillment_status',
        'item_shipped_at',
        'item_delivered_at',
        'product_attributes',
        'item_metadata',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'product_snapshot' => 'array',
            'package_snapshot' => 'array',
            'package_items_snapshot' => 'array',
            // Platform fields
            'platform_discount' => 'decimal:2',
            'seller_discount' => 'decimal:2',
            'unit_original_price' => 'decimal:2',
            'subtotal_before_discount' => 'decimal:2',
            'item_weight_kg' => 'decimal:3',
            'item_shipped_at' => 'datetime',
            'item_delivered_at' => 'datetime',
            'product_attributes' => 'array',
            'item_metadata' => 'array',
        ];
    }

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'order_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Type checking methods
    public function isProduct(): bool
    {
        return $this->itemable_type === Product::class || ($this->product_id && ! $this->package_id);
    }

    public function isPackage(): bool
    {
        return $this->itemable_type === Package::class || $this->package_id !== null;
    }

    // Helper methods
    public function getDisplayName(): string
    {
        if ($this->variant_name) {
            return $this->product_name.' - '.$this->variant_name;
        }

        return $this->product_name;
    }

    public function getPendingQuantity(): int
    {
        return $this->quantity_ordered - $this->quantity_shipped - $this->quantity_cancelled;
    }

    public function canBeCancelled(): bool
    {
        return $this->getPendingQuantity() > 0;
    }

    public function canBeShipped(): bool
    {
        return $this->getPendingQuantity() > 0;
    }

    public function ship(int $quantity): void
    {
        if ($quantity > $this->getPendingQuantity()) {
            throw new \InvalidArgumentException('Cannot ship more than pending quantity');
        }

        $this->update([
            'quantity_shipped' => $this->quantity_shipped + $quantity,
        ]);
    }

    public function cancel(int $quantity): void
    {
        if ($quantity > $this->getPendingQuantity()) {
            throw new \InvalidArgumentException('Cannot cancel more than pending quantity');
        }

        $this->update([
            'quantity_cancelled' => $this->quantity_cancelled + $quantity,
        ]);
    }

    public function isFullyShipped(): bool
    {
        return $this->quantity_shipped >= $this->quantity_ordered;
    }

    public function isPartiallyCancelled(): bool
    {
        return $this->quantity_cancelled > 0 && $this->quantity_cancelled < $this->quantity_ordered;
    }

    public function isFullyCancelled(): bool
    {
        return $this->quantity_cancelled >= $this->quantity_ordered;
    }

    // Platform-specific helper methods
    public function getTotalDiscountAttribute(): float
    {
        return $this->platform_discount + $this->seller_discount;
    }

    public function getEffectiveQuantityAttribute(): int
    {
        return $this->quantity_ordered - $this->returned_quantity - $this->quantity_cancelled;
    }

    public function getDisplayNameAttribute(): string
    {
        // Use platform name if available
        if ($this->platform_product_name) {
            $name = $this->platform_product_name;
            if ($this->platform_variation_name) {
                $name .= ' - '.$this->platform_variation_name;
            }

            return $name;
        }

        // Fall back to regular name
        if ($this->variant_name) {
            return $this->product_name.' - '.$this->variant_name;
        }

        return $this->product_name;
    }

    public function hasReturns(): bool
    {
        return $this->returned_quantity > 0;
    }

    public function getDiscountPercentageAttribute(): float
    {
        $subtotal = $this->subtotal_before_discount ?: ($this->unit_price * $this->quantity_ordered);

        if ($subtotal <= 0) {
            return 0;
        }

        return round(($this->getTotalDiscountAttribute() / $subtotal) * 100, 2);
    }

    /**
     * Deduct stock for this order item
     * If it's a package, deduct stock for all products in the package
     */
    public function deductStock(): void
    {
        if ($this->isPackage() && $this->package) {
            $this->deductPackageStock();
        } elseif ($this->isProduct() && $this->product) {
            $this->deductProductStock();
        }
    }

    /**
     * Deduct stock for a package item
     */
    protected function deductPackageStock(): void
    {
        $package = $this->package;

        if (! $package || ! $package->track_stock) {
            return;
        }

        // Get warehouse from order item or use package default
        $warehouseId = $this->warehouse_id ?? $package->default_warehouse_id;

        // Deduct stock for each product in the package
        foreach ($package->products as $product) {
            $requiredQuantity = $product->pivot->quantity * $this->quantity_ordered;
            $productWarehouseId = $warehouseId ?? $product->pivot->warehouse_id;

            $stockLevel = $product->stockLevels()
                ->where('warehouse_id', $productWarehouseId)
                ->first();

            if ($stockLevel) {
                // Record quantity before deduction
                $quantityBefore = $stockLevel->quantity;

                // Deduct from stock
                $stockLevel->decrement('quantity', $requiredQuantity);

                // Refresh to get updated quantity
                $stockLevel->refresh();
                $quantityAfter = $stockLevel->quantity;

                // Update last movement timestamp
                $stockLevel->update(['last_movement_at' => now()]);

                // Create stock movement record
                StockMovement::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $product->pivot->product_variant_id ?? null,
                    'warehouse_id' => $productWarehouseId,
                    'type' => 'out',
                    'quantity' => -$requiredQuantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'unit_cost' => $stockLevel->average_cost ?? 0,
                    'reference_type' => ProductOrder::class,
                    'reference_id' => $this->order_id,
                    'notes' => "Package order sale: {$package->name} (Order #{$this->order->order_number})",
                    'metadata' => [
                        'order_item_id' => $this->id,
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'product_quantity_in_package' => $product->pivot->quantity,
                        'packages_ordered' => $this->quantity_ordered,
                        'total_product_quantity' => $requiredQuantity,
                    ],
                ]);
            }
        }
    }

    /**
     * Deduct stock for a regular product item
     */
    protected function deductProductStock(): void
    {
        $product = $this->product;

        if (! $product || ! $product->shouldTrackQuantity()) {
            return;
        }

        $stockLevel = $product->stockLevels()
            ->where('warehouse_id', $this->warehouse_id)
            ->first();

        if ($stockLevel) {
            // Record quantity before deduction
            $quantityBefore = $stockLevel->quantity;

            // Deduct from stock
            $stockLevel->decrement('quantity', $this->quantity_ordered);

            // Refresh to get updated quantity
            $stockLevel->refresh();
            $quantityAfter = $stockLevel->quantity;

            // Update last movement timestamp
            $stockLevel->update(['last_movement_at' => now()]);

            // Create stock movement record
            StockMovement::create([
                'product_id' => $product->id,
                'product_variant_id' => $this->product_variant_id ?? null,
                'warehouse_id' => $this->warehouse_id,
                'type' => 'out',
                'quantity' => -$this->quantity_ordered,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $stockLevel->average_cost ?? 0,
                'reference_type' => ProductOrder::class,
                'reference_id' => $this->order_id,
                'notes' => "Product order sale (Order #{$this->order->order_number})",
                'metadata' => [
                    'order_item_id' => $this->id,
                    'product_name' => $product->name,
                ],
            ]);
        }
    }

    /**
     * Check if sufficient stock is available for this order item
     */
    public function hasInsufficientStock(): array
    {
        $insufficientItems = [];

        if ($this->isPackage() && $this->package) {
            $package = $this->package;

            if (! $package->track_stock) {
                return [];
            }

            $warehouseId = $this->warehouse_id ?? $package->default_warehouse_id;

            foreach ($package->products as $product) {
                $quantityPerPackage = $product->pivot->quantity;
                $totalQuantityNeeded = $quantityPerPackage * $this->quantity_ordered;
                $productWarehouseId = $warehouseId ?? $product->pivot->warehouse_id;

                $stockLevel = $product->stockLevels()
                    ->where('warehouse_id', $productWarehouseId)
                    ->first();

                $availableStock = $stockLevel ? $stockLevel->quantity : 0;

                if ($availableStock < $totalQuantityNeeded) {
                    $insufficientItems[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'needed' => $totalQuantityNeeded,
                        'available' => $availableStock,
                        'shortage' => $totalQuantityNeeded - $availableStock,
                        'package_name' => $package->name,
                    ];
                }
            }
        } elseif ($this->isProduct() && $this->product) {
            $product = $this->product;

            if (! $product->shouldTrackQuantity()) {
                return [];
            }

            $stockLevel = $product->stockLevels()
                ->where('warehouse_id', $this->warehouse_id)
                ->first();

            $availableStock = $stockLevel ? $stockLevel->quantity : 0;

            if ($availableStock < $this->quantity_ordered) {
                $insufficientItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'needed' => $this->quantity_ordered,
                    'available' => $availableStock,
                    'shortage' => $this->quantity_ordered - $availableStock,
                ];
            }
        }

        return $insufficientItems;
    }
}
