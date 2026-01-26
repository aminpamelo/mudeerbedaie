<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelOrderBump extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_step_id',
        'product_id',
        'course_id',
        'headline',
        'description',
        'price',
        'compare_at_price',
        'image_url',
        'is_checked_by_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'is_checked_by_default' => 'boolean',
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

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // Helpers
    public function hasDiscount(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->price;
    }

    public function getDiscountPercentage(): int
    {
        if (! $this->hasDiscount()) {
            return 0;
        }

        return (int) round((1 - ($this->price / $this->compare_at_price)) * 100);
    }

    public function getSavingsAmount(): float
    {
        if (! $this->hasDiscount()) {
            return 0;
        }

        return $this->compare_at_price - $this->price;
    }

    public function getFormattedPrice(): string
    {
        return 'RM '.number_format($this->price, 2);
    }

    public function getFormattedCompareAtPrice(): string
    {
        return 'RM '.number_format($this->compare_at_price, 2);
    }

    public function isProduct(): bool
    {
        return $this->product_id !== null;
    }

    public function isCourse(): bool
    {
        return $this->course_id !== null;
    }

    public function getImageUrl(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        if ($this->course) {
            return $this->course->thumbnail_url ?? null;
        }

        return $this->product?->primaryImage?->url ?? null;
    }
}
