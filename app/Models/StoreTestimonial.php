<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A customer testimonial rendered on the storefront homepage.
 *
 * Only real, admin-entered rows exist — nothing is seeded — and the homepage
 * section is skipped entirely when the table is empty.
 */
class StoreTestimonial extends Model
{
    protected $fillable = [
        'author_name',
        'author_title',
        'author_photo_path',
        'quote',
        'rating',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rating' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->author_photo_path ? Storage::disk('public')->url($this->author_photo_path) : null;
    }

    /**
     * First letter of the author's name, for the fallback avatar tile.
     */
    public function getInitialAttribute(): string
    {
        return mb_strtoupper(mb_substr(trim($this->author_name), 0, 1));
    }
}
