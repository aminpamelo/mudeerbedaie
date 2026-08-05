<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A campaign slide in the storefront hero carousel.
 *
 * These sit *after* the built-in brand slide, so an empty table is a valid
 * state — the hero simply renders as a single static brand panel.
 */
class StoreBanner extends Model
{
    protected $fillable = [
        'eyebrow',
        'title',
        'subtitle',
        'image_path',
        'cta_text',
        'cta_url',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Live slides only: enabled, already started and not yet expired. An
     * open-ended window (both dates null) means "show until switched off".
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Public URL for the slide artwork, or null when none was uploaded.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * Whether this slide is currently within its scheduling window — used by
     * the admin list to explain why an enabled banner isn't showing yet.
     */
    public function isScheduledNow(): bool
    {
        $started = $this->starts_at === null || $this->starts_at->isPast();
        $notEnded = $this->ends_at === null || $this->ends_at->isFuture();

        return $started && $notEnded;
    }
}
