<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSyncSchedule extends Model
{
    protected $fillable = [
        'platform_account_id',
        'sync_type',
        'interval_minutes',
        'last_run_at',
        'next_run_at',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('sync_type', $type);
    }

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->next_run_at === null || $this->next_run_at->isPast();
    }

    public function markAsRun(): void
    {
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => now()->addMinutes($this->interval_minutes),
        ]);
    }

    public function calculateNextRun(): void
    {
        $this->update([
            'next_run_at' => now()->addMinutes($this->interval_minutes),
        ]);
    }
}
