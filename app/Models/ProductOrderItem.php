<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOrderItem extends Model
{
    protected $fillable = [
        'order_id',
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
}
