<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FunnelProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_step_id',
        'product_id',
        'product_variant_id',
        'course_id',
        'package_id',
        'type',
        'name',
        'description',
        'image_url',
        'funnel_price',
        'compare_at_price',
        'is_recurring',
        'billing_interval',
        'sort_order',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'funnel_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'is_recurring' => 'boolean',
            'settings' => 'array',
        ];
    }

    // Relationships
    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'funnel_step_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function commissionRule(): HasOne
    {
        return $this->hasOne(FunnelAffiliateCommissionRule::class);
    }

    // Helpers
    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->package) {
            return $this->package->name;
        }

        if ($this->course) {
            return $this->course->name;
        }

        if ($this->productVariant) {
            return $this->product->name.' - '.$this->productVariant->name;
        }

        return $this->product?->name ?? 'Unknown Product';
    }

    public function getDisplayDescription(): string
    {
        if ($this->description) {
            return $this->description;
        }

        if ($this->package) {
            return $this->package->short_description ?? $this->package->description ?? '';
        }

        if ($this->course) {
            return $this->course->description ?? '';
        }

        return $this->product?->description ?? '';
    }

    public function getImageUrl(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        if ($this->package) {
            return $this->package->featured_image ?? null;
        }

        if ($this->course) {
            return $this->course->thumbnail_url ?? null;
        }

        return $this->product?->primaryImage?->url ?? null;
    }

    public function getPrice(): float
    {
        return $this->funnel_price;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->funnel_price;
    }

    public function getDiscountPercentage(): int
    {
        if (! $this->hasDiscount()) {
            return 0;
        }

        return (int) round((1 - ($this->funnel_price / $this->compare_at_price)) * 100);
    }

    public function getSavingsAmount(): float
    {
        if (! $this->hasDiscount()) {
            return 0;
        }

        return $this->compare_at_price - $this->funnel_price;
    }

    public function isProduct(): bool
    {
        return $this->product_id !== null;
    }

    public function isCourse(): bool
    {
        return $this->course_id !== null;
    }

    public function isPackage(): bool
    {
        return $this->package_id !== null;
    }

    public function isMain(): bool
    {
        return $this->type === 'main';
    }

    public function isUpsell(): bool
    {
        return $this->type === 'upsell';
    }

    public function isDownsell(): bool
    {
        return $this->type === 'downsell';
    }

    public function isBump(): bool
    {
        return $this->type === 'bump';
    }

    public function getFormattedPrice(): string
    {
        return 'RM '.number_format($this->funnel_price, 2);
    }

    public function getFormattedCompareAtPrice(): string
    {
        return 'RM '.number_format($this->compare_at_price, 2);
    }

    // Scopes
    public function scopeMain($query)
    {
        return $query->where('type', 'main');
    }

    public function scopeUpsell($query)
    {
        return $query->where('type', 'upsell');
    }

    public function scopeDownsell($query)
    {
        return $query->where('type', 'downsell');
    }
}
