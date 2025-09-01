<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'data',
        'processed',
        'processed_at',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'data' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'timestamp',
    ];

    // Scopes
    public function scopeProcessed($query)
    {
        return $query->where('processed', true);
    }

    public function scopePending($query)
    {
        return $query->where('processed', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeFailed($query)
    {
        return $query->where('processed', false)
            ->whereNotNull('error_message');
    }

    // Helper methods
    public function markAsProcessed(): void
    {
        $this->update([
            'processed' => true,
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'processed' => false,
            'error_message' => $error,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    public static function createFromStripeEvent($event): self
    {
        return self::create([
            'stripe_event_id' => $event->id,
            'type' => $event->type,
            'data' => $event->toArray(),
        ]);
    }

    public function canRetry(): bool
    {
        return $this->retry_count < 3 && ! $this->processed;
    }
}
