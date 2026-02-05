<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class FunnelSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'user_id',
        'student_id',
        'affiliate_id',
        'visitor_id',
        'email',
        'phone',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'device_type',
        'browser',
        'country_code',
        'entry_step_id',
        'current_step_id',
        'status',
        'started_at',
        'last_activity_at',
        'converted_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'converted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FunnelSession $session) {
            $session->uuid = $session->uuid ?? Str::uuid()->toString();
            $session->started_at = $session->started_at ?? now();
            $session->last_activity_at = $session->last_activity_at ?? now();
        });
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(FunnelAffiliate::class, 'affiliate_id');
    }

    public function entryStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'entry_step_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'current_step_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FunnelSessionEvent::class, 'session_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FunnelOrder::class, 'session_id');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(FunnelCart::class, 'session_id');
    }

    // Status helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isAbandoned(): bool
    {
        return $this->status === 'abandoned';
    }

    public function markAsConverted(): void
    {
        $this->update([
            'status' => 'converted',
            'converted_at' => now(),
        ]);
    }

    public function markAsAbandoned(): void
    {
        $this->update(['status' => 'abandoned']);
    }

    // Activity tracking
    public function updateActivity(?FunnelStep $step = null): void
    {
        $data = ['last_activity_at' => now()];

        if ($step) {
            $data['current_step_id'] = $step->id;
        }

        $this->update($data);
    }

    public function trackEvent(string $eventType, array $eventData = [], ?FunnelStep $step = null): FunnelSessionEvent
    {
        $this->updateActivity($step);

        return $this->events()->create([
            'step_id' => $step?->id ?? $this->current_step_id,
            'event_type' => $eventType,
            'event_data' => $eventData,
        ]);
    }

    /**
     * Track event only once per session/step combination.
     * Returns existing event if already tracked, or creates new one.
     */
    public function trackEventOnce(string $eventType, array $eventData = [], ?FunnelStep $step = null): FunnelSessionEvent
    {
        $stepId = $step?->id ?? $this->current_step_id;

        // Check if this event type has already been tracked for this step
        $existingEvent = $this->events()
            ->where('event_type', $eventType)
            ->where('step_id', $stepId)
            ->first();

        if ($existingEvent) {
            $this->updateActivity($step);

            return $existingEvent;
        }

        return $this->trackEvent($eventType, $eventData, $step);
    }

    // UTM helpers
    public function hasUtmData(): bool
    {
        return $this->utm_source || $this->utm_medium || $this->utm_campaign;
    }

    public function getUtmData(): array
    {
        return [
            'source' => $this->utm_source,
            'medium' => $this->utm_medium,
            'campaign' => $this->utm_campaign,
            'content' => $this->utm_content,
            'term' => $this->utm_term,
        ];
    }

    // Analytics
    public function getTotalRevenue(): float
    {
        return $this->orders()->sum('funnel_revenue');
    }

    public function getSessionDuration(): int
    {
        return $this->started_at->diffInSeconds($this->last_activity_at);
    }

    public function getPageViewCount(): int
    {
        return $this->events()->where('event_type', 'page_view')->count();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }

    public function scopeAbandoned($query)
    {
        return $query->where('status', 'abandoned');
    }

    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email');
    }

    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('last_activity_at', '>=', now()->subHours($hours));
    }
}
