<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveScheduleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'time_slot_id',
        'live_host_id',
        'day_of_week',
        'schedule_date',
        'remarks',
        'status',
        'is_template',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'is_template' => 'boolean',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(LiveTimeSlot::class, 'time_slot_id');
    }

    public function liveHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'live_host_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeTemplate(Builder $query): Builder
    {
        return $query->where('is_template', true);
    }

    public function scopeSpecificDate(Builder $query): Builder
    {
        return $query->where('is_template', false);
    }

    public function scopeForPlatform(Builder $query, int $platformAccountId): Builder
    {
        return $query->where('platform_account_id', $platformAccountId);
    }

    public function scopeForDay(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    public function scopeForHost(Builder $query, int $hostId): Builder
    {
        return $query->where('live_host_id', $hostId);
    }

    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->where('schedule_date', $date);
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('live_host_id');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('live_host_id');
    }

    public function getDayNameAttribute(): string
    {
        $days = [
            0 => 'Ahad',      // Sunday
            1 => 'Isnin',     // Monday
            2 => 'Selasa',    // Tuesday
            3 => 'Rabu',      // Wednesday
            4 => 'Khamis',    // Thursday
            5 => 'Jumaat',    // Friday
            6 => 'Sabtu',     // Saturday
        ];

        return $days[$this->day_of_week] ?? 'Unknown';
    }

    public function getDayNameEnAttribute(): string
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

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'blue',
            'confirmed' => 'green',
            'in_progress' => 'yellow',
            'completed' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function isAssigned(): bool
    {
        return $this->live_host_id !== null;
    }
}
