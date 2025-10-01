<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'total_price',
        'product_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'product_snapshot' => 'array',
        ];
    }

    // Relationships
    public function cart(): BelongsTo
    {
        return $this->belongsTo(ProductCart::class, 'cart_id');
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
    public function updateQuantity(int $quantity): void
    {
        $this->update([
            'quantity' => $quantity,
            'total_price' => $this->unit_price * $quantity,
        ]);

        $this->cart->recalculateTotal();
    }

    public function checkStockAvailability(): bool
    {
        if ($this->product_variant_id) {
            return $this->variant->checkStockAvailability($this->quantity, $this->warehouse_id);
        }

        return $this->product->checkStockAvailability($this->quantity, $this->warehouse_id);
    }

    public function getDisplayName(): string
    {
        if ($this->variant) {
            return $this->product->name.' - '.$this->variant->name;
        }

        return $this->product->name;
    }

    public function getSku(): string
    {
        return $this->variant?->sku ?? $this->product->sku;
    }
}
