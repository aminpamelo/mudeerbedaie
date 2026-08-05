<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductCartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'itemable_type',
        'itemable_id',
        'product_id',
        'product_variant_id',
        'package_id',
        'course_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'total_price',
        'product_snapshot',
        'package_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'product_snapshot' => 'array',
            'package_snapshot' => 'array',
        ];
    }

    // Relationships
    public function cart(): BelongsTo
    {
        return $this->belongsTo(ProductCart::class, 'cart_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isPackage(): bool
    {
        return $this->package_id !== null || $this->itemable_type === Package::class;
    }

    public function isCourse(): bool
    {
        return $this->course_id !== null || $this->itemable_type === Course::class;
    }

    public function isProduct(): bool
    {
        return ! $this->isPackage() && ! $this->isCourse();
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
        if ($this->isPackage()) {
            return (bool) ($this->package?->checkStockAvailability()['available'] ?? true);
        }

        if ($this->isCourse()) {
            return true; // courses have no inventory
        }

        if ($this->product_variant_id) {
            return $this->variant->checkStockAvailability($this->quantity, $this->warehouse_id);
        }

        return $this->product->checkStockAvailability($this->quantity, $this->warehouse_id);
    }

    public function getDisplayName(): string
    {
        if ($this->isPackage()) {
            return $this->package?->name ?? ($this->product_snapshot['name'] ?? 'Pakej');
        }

        if ($this->isCourse()) {
            return $this->course?->name ?? ($this->product_snapshot['name'] ?? 'Kursus');
        }

        if ($this->variant) {
            return $this->product->name.' - '.$this->variant->name;
        }

        return $this->product?->name ?? '';
    }

    public function getSku(): ?string
    {
        if ($this->isPackage()) {
            return 'PKG-'.$this->package_id;
        }

        if ($this->isCourse()) {
            return 'CRS-'.$this->course_id;
        }

        return $this->variant?->sku ?? $this->product?->sku;
    }

    public function getImageUrl(): ?string
    {
        if ($this->isPackage()) {
            return $this->package?->featured_image;
        }

        if ($this->isCourse()) {
            return $this->course?->thumbnail_url;
        }

        return $this->product?->primaryImage?->url;
    }
}
