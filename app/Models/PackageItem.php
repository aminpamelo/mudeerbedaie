<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'itemable_type',
        'itemable_id',
        'quantity',
        'product_variant_id',
        'warehouse_id',
        'custom_price',
        'original_price',
        'sort_order',
        'is_featured',
        'package_description',
    ];

    protected function casts(): array
    {
        return [
            'custom_price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'is_featured' => 'boolean',
        ];
    }

    // Relationships
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Helper methods
    public function isProduct(): bool
    {
        return $this->itemable_type === Product::class;
    }

    public function isCourse(): bool
    {
        return $this->itemable_type === Course::class;
    }

    public function isClass(): bool
    {
        return $this->itemable_type === ClassModel::class;
    }

    public function getEffectivePrice(): float
    {
        if ($this->custom_price) {
            return $this->custom_price;
        }

        if ($this->isProduct()) {
            $product = $this->itemable;
            if ($this->product_variant_id && $this->productVariant) {
                return $this->productVariant->price;
            }

            return $product->base_price;
        }

        if ($this->isCourse()) {
            $course = $this->itemable;

            return $course->feeSettings->fee_amount ?? 0;
        }

        if ($this->isClass()) {
            $class = $this->itemable;

            return $class->course?->feeSettings->fee_amount ?? 0;
        }

        return 0;
    }

    public function getTotalPrice(): float
    {
        return $this->getEffectivePrice() * $this->quantity;
    }

    public function getDisplayName(): string
    {
        if ($this->isProduct()) {
            $name = $this->itemable->name;
            if ($this->productVariant) {
                $name .= ' - '.$this->productVariant->name;
            }

            return $name;
        }

        if ($this->isCourse()) {
            return $this->itemable->name;
        }

        if ($this->isClass()) {
            $class = $this->itemable;

            return $class->title.' ('.$class->course?->name.')';
        }

        return 'Unknown Item';
    }

    public function getDisplayDescription(): string
    {
        if ($this->package_description) {
            return $this->package_description;
        }

        if ($this->isProduct()) {
            return $this->itemable->short_description ?? $this->itemable->description ?? '';
        }

        if ($this->isCourse()) {
            return $this->itemable->description ?? '';
        }

        if ($this->isClass()) {
            return $this->itemable->description ?? '';
        }

        return '';
    }

    public function checkStockAvailability(): array
    {
        if (! $this->isProduct()) {
            return [
                'available' => true,
                'message' => 'No stock check required for courses/classes',
            ];
        }

        $product = $this->itemable;
        if (! $product->shouldTrackQuantity()) {
            return [
                'available' => true,
                'message' => 'Product does not track quantity',
            ];
        }

        $availableStock = $product->getStockQuantity($this->warehouse_id);

        return [
            'available' => $availableStock >= $this->quantity,
            'required' => $this->quantity,
            'available_stock' => $availableStock,
            'message' => $availableStock >= $this->quantity
                ? 'Stock available'
                : "Insufficient stock. Required: {$this->quantity}, Available: {$availableStock}",
        ];
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'RM '.number_format($this->getEffectivePrice(), 2);
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return 'RM '.number_format($this->getTotalPrice(), 2);
    }

    public function getFormattedOriginalPriceAttribute(): string
    {
        return 'RM '.number_format($this->original_price, 2);
    }

    // Scopes
    public function scopeProducts($query)
    {
        return $query->where('itemable_type', Product::class);
    }

    public function scopeCourses($query)
    {
        return $query->where('itemable_type', Course::class);
    }

    public function scopeClasses($query)
    {
        return $query->where('itemable_type', ClassModel::class);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
