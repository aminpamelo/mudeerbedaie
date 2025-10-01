<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCart extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'currency',
        'subtotal',
        'tax_amount',
        'total_amount',
        'coupon_code',
        'discount_amount',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductCartItem::class, 'cart_id');
    }

    // Helper methods
    public function addItem(Product $product, ?ProductVariant $variant = null, int $quantity = 1, ?Warehouse $warehouse = null): ProductCartItem
    {
        $item = $this->items()->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        if ($item) {
            $item->update(['quantity' => $item->quantity + $quantity]);
        } else {
            $price = $variant ? $variant->price : $product->base_price;
            $item = $this->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'warehouse_id' => $warehouse?->id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $price * $quantity,
                'product_snapshot' => $this->buildProductSnapshot($product, $variant),
            ]);
        }

        $this->recalculateTotal();

        return $item;
    }

    public function removeItem(ProductCartItem $item): bool
    {
        $removed = $item->delete();
        if ($removed) {
            $this->recalculateTotal();
        }

        return $removed;
    }

    public function recalculateTotal(): void
    {
        $this->load('items');
        $subtotal = $this->items->sum('total_price');
        $taxAmount = $subtotal * 0.06; // 6% GST - adjust as needed

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount - $this->discount_amount,
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->items()->count() === 0;
    }

    public function clear(): void
    {
        $this->items()->delete();
        $this->update([
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'discount_amount' => 0,
            'coupon_code' => null,
        ]);
    }

    private function buildProductSnapshot(Product $product, ?ProductVariant $variant = null): array
    {
        return [
            'product_name' => $product->name,
            'product_sku' => $variant ? $variant->sku : $product->sku,
            'variant_name' => $variant?->name,
            'variant_attributes' => $variant?->attributes,
            'price' => $variant ? $variant->price : $product->base_price,
            'cost_price' => $variant ? $variant->cost_price : $product->cost_price,
        ];
    }
}
