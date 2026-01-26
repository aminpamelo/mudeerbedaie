<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelSessionEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'step_id',
        'event_type',
        'event_data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FunnelSessionEvent $event) {
            $event->created_at = $event->created_at ?? now();
        });
    }

    // Relationships
    public function session(): BelongsTo
    {
        return $this->belongsTo(FunnelSession::class, 'session_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'step_id');
    }

    // Event type helpers
    public function isPageView(): bool
    {
        return $this->event_type === 'page_view';
    }

    public function isFormSubmit(): bool
    {
        return $this->event_type === 'form_submit';
    }

    public function isAddToCart(): bool
    {
        return $this->event_type === 'add_to_cart';
    }

    public function isPurchase(): bool
    {
        return $this->event_type === 'purchase';
    }

    public function isClick(): bool
    {
        return $this->event_type === 'click';
    }

    public function isScroll(): bool
    {
        return $this->event_type === 'scroll';
    }

    public function isUpsellAccepted(): bool
    {
        return $this->event_type === 'upsell_accepted';
    }

    public function isUpsellDeclined(): bool
    {
        return $this->event_type === 'upsell_declined';
    }

    public function isDownsellAccepted(): bool
    {
        return $this->event_type === 'downsell_accepted';
    }

    public function isDownsellDeclined(): bool
    {
        return $this->event_type === 'downsell_declined';
    }

    public function isBumpAccepted(): bool
    {
        return $this->event_type === 'bump_accepted';
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeForStep($query, int $stepId)
    {
        return $query->where('step_id', $stepId);
    }

    public function scopePageViews($query)
    {
        return $query->where('event_type', 'page_view');
    }

    public function scopePurchases($query)
    {
        return $query->where('event_type', 'purchase');
    }
}
