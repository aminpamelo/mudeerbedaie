<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\LiveScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_recurring',
        'is_active',
        'live_host_id',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function liveHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'live_host_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('is_recurring', true);
    }

    public function scopeForDay(Builder $query, int $day): Builder
    {
        return $query->where('day_of_week', $day);
    }

    public function getDayNameAttribute(): string
    {
        return match ($this->day_of_week) {
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            default => 'Unknown',
        };
    }

    public function getTimeRangeAttribute(): string
    {
        $start = \Carbon\Carbon::parse($this->start_time)->format('g:ia');
        $end = \Carbon\Carbon::parse($this->end_time)->format('g:ia');

        return "{$start} - {$end}";
    }

    public function getDayNameMsAttribute(): string
    {
        return match ($this->day_of_week) {
            0 => 'Ahad',
            1 => 'Isnin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Khamis',
            5 => 'Jumaat',
            6 => 'Sabtu',
            default => 'Unknown',
        };
    }

    public function scopeForPlatform(Builder $query, int $platformAccountId): Builder
    {
        return $query->where('platform_account_id', $platformAccountId);
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('live_host_id');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('live_host_id');
    }

    public function isAssigned(): bool
    {
        return $this->live_host_id !== null;
    }
}
