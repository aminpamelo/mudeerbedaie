<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TikTokWebhookEvent extends Model
{
    protected $table = 'tiktok_webhook_events';

    protected $fillable = [
        'platform_account_id',
        'event_type',
        'event_id',
        'payload',
        'status',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeByEventType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this event has already been processed (idempotency check).
     */
    public static function isAlreadyProcessed(string $eventId): bool
    {
        return self::where('event_id', $eventId)
            ->where('status', 'processed')
            ->exists();
    }
}
