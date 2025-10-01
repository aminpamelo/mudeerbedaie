<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformWebhook extends Model
{
    protected $fillable = [
        'platform_id',
        'platform_account_id',
        'name',
        'event_type',
        'endpoint_url',
        'secret',
        'method',
        'headers',
        'payload_template',
        'is_active',
        'verify_ssl',
        'timeout_seconds',
        'retry_attempts',
        'last_triggered_at',
        'last_success_at',
        'last_failure_at',
        'last_error',
        'total_calls',
        'successful_calls',
        'failed_calls',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload_template' => 'array',
            'is_active' => 'boolean',
            'verify_ssl' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_calls === 0) {
            return 0;
        }

        return round(($this->successful_calls / $this->total_calls) * 100, 2);
    }

    public function recordCall(bool $success, ?string $error = null): void
    {
        $this->increment('total_calls');

        if ($success) {
            $this->increment('successful_calls');
            $this->update([
                'last_success_at' => now(),
                'last_triggered_at' => now(),
            ]);
        } else {
            $this->increment('failed_calls');
            $this->update([
                'last_failure_at' => now(),
                'last_triggered_at' => now(),
                'last_error' => $error,
            ]);
        }
    }
}
