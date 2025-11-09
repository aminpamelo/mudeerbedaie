<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LiveSession extends Model
{
    /** @use HasFactory<\Database\Factories\LiveSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'live_schedule_id',
        'title',
        'description',
        'status',
        'scheduled_start_at',
        'actual_start_at',
        'actual_end_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function liveSchedule(): BelongsTo
    {
        return $this->belongsTo(LiveSchedule::class);
    }

    public function analytics(): HasOne
    {
        return $this->hasOne(LiveAnalytics::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LiveSessionAttachment::class);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live');
    }

    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('status', 'ended');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_start_at', '>', now())
            ->orderBy('scheduled_start_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereIn('status', ['ended', 'cancelled'])
            ->orderByDesc('scheduled_start_at');
    }

    public function startLive(): void
    {
        $this->update([
            'status' => 'live',
            'actual_start_at' => now(),
        ]);
    }

    public function endLive(): void
    {
        $this->update([
            'status' => 'ended',
            'actual_end_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->actual_start_at && $this->actual_end_at) {
            return $this->actual_start_at->diffInMinutes($this->actual_end_at);
        }

        return null;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'blue',
            'live' => 'green',
            'ended' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
