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
            $newQuantity = $item->quantity + $quantity;
            $item->update([
                'quantity' => $newQuantity,
                'total_price' => $item->unit_price * $newQuantity,
            ]);
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

    /**
     * Add a package as a single cart line (merging quantity if already present).
     * The package price is a flat bundle price, so quantity multiplies it.
     */
    public function addPackage(Package $package, int $quantity = 1): ProductCartItem
    {
        $item = $this->items()->where('package_id', $package->id)->first();

        if ($item) {
            $item->update([
                'quantity' => $item->quantity + $quantity,
                'total_price' => $item->unit_price * ($item->quantity + $quantity),
            ]);
        } else {
            $price = $package->price;
            $item = $this->items()->create([
                'itemable_type' => Package::class,
                'itemable_id' => $package->id,
                'package_id' => $package->id,
                'warehouse_id' => $package->default_warehouse_id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $price * $quantity,
                'package_snapshot' => $this->buildPackageSnapshot($package),
            ]);
        }

        $this->recalculateTotal();

        return $item;
    }

    /**
     * Add a course as a one-time enrolment line. A course is bought once, so the
     * quantity is always 1 and re-adding the same course is a no-op.
     */
    public function addCourse(Course $course): ProductCartItem
    {
        $existing = $this->items()->where('course_id', $course->id)->first();

        if ($existing) {
            return $existing;
        }

        $price = (float) ($course->feeSettings?->fee_amount ?? 0);

        $item = $this->items()->create([
            'itemable_type' => Course::class,
            'itemable_id' => $course->id,
            'course_id' => $course->id,
            'quantity' => 1,
            'unit_price' => $price,
            'total_price' => $price,
            'product_snapshot' => [
                'name' => $course->name,
                'slug' => $course->slug,
                'price' => $price,
            ],
        ]);

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

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'total_amount' => $subtotal - $this->discount_amount,
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

    /**
     * @return array<string, mixed>
     */
    private function buildPackageSnapshot(Package $package): array
    {
        return [
            'name' => $package->name,
            'slug' => $package->slug,
            'price' => $package->price,
            'original_price' => $package->calculateOriginalPrice(),
            'featured_image' => $package->featured_image,
            'items' => $package->loadMissing('items')->items->toArray(),
        ];
    }
}
