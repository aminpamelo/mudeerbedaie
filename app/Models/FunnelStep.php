<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FunnelStep extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'funnel_id',
        'name',
        'slug',
        'type',
        'sort_order',
        'is_active',
        'settings',
        'next_step_id',
        'decline_step_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function content(): HasOne
    {
        return $this->hasOne(FunnelStepContent::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(FunnelStepContent::class);
    }

    public function draftContent(): HasOne
    {
        return $this->hasOne(FunnelStepContent::class)->latest();
    }

    public function publishedContent(): HasOne
    {
        return $this->hasOne(FunnelStepContent::class)->where('is_published', true)->latest('published_at');
    }

    public function products(): HasMany
    {
        return $this->hasMany(FunnelProduct::class)->orderBy('sort_order');
    }

    public function mainProducts(): HasMany
    {
        return $this->hasMany(FunnelProduct::class)->where('type', 'main')->orderBy('sort_order');
    }

    public function upsellProducts(): HasMany
    {
        return $this->hasMany(FunnelProduct::class)->where('type', 'upsell')->orderBy('sort_order');
    }

    public function downsellProducts(): HasMany
    {
        return $this->hasMany(FunnelProduct::class)->where('type', 'downsell')->orderBy('sort_order');
    }

    public function orderBumps(): HasMany
    {
        return $this->hasMany(FunnelOrderBump::class)->orderBy('sort_order');
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'next_step_id');
    }

    public function declineStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'decline_step_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FunnelSession::class, 'current_step_id');
    }

    // Type helpers
    public function isLanding(): bool
    {
        return $this->type === 'landing';
    }

    public function isSales(): bool
    {
        return $this->type === 'sales';
    }

    public function isCheckout(): bool
    {
        return $this->type === 'checkout';
    }

    public function isUpsell(): bool
    {
        return $this->type === 'upsell';
    }

    public function isDownsell(): bool
    {
        return $this->type === 'downsell';
    }

    public function isThankYou(): bool
    {
        return $this->type === 'thankyou';
    }

    public function isOptin(): bool
    {
        return $this->type === 'optin';
    }

    // URL helpers
    public function getPublicUrl(): string
    {
        return url("/f/{$this->funnel->slug}/{$this->slug}");
    }

    public function getNextStepUrl(): ?string
    {
        return $this->nextStep?->getPublicUrl();
    }

    public function getDeclineStepUrl(): ?string
    {
        return $this->declineStep?->getPublicUrl();
    }

    // Content helpers
    public function getContentJson(): array
    {
        return $this->content?->content ?? ['content' => [], 'root' => []];
    }

    public function hasContent(): bool
    {
        return $this->content !== null && ! empty($this->content->content);
    }

    public function hasPublishedContent(): bool
    {
        return $this->content !== null && $this->content->is_published;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
