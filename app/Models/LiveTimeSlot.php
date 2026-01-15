<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveTimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'day_of_week',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_active',
        'sort_order',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'day_of_week' => 'integer',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(LiveScheduleAssignment::class, 'time_slot_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('start_time');
    }

    public function scopeForPlatform(Builder $query, int $platformAccountId): Builder
    {
        return $query->where('platform_account_id', $platformAccountId);
    }

    public function scopeForDay(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where(function ($q) use ($dayOfWeek) {
            $q->where('day_of_week', $dayOfWeek)
                ->orWhereNull('day_of_week');
        });
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('platform_account_id');
    }

    public function getDayNameAttribute(): ?string
    {
        if ($this->day_of_week === null) {
            return 'All Days';
        }

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

    public function getDayNameMsAttribute(): ?string
    {
        if ($this->day_of_week === null) {
            return 'Semua Hari';
        }

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

    public function getTimeRangeAttribute(): string
    {
        $start = \Carbon\Carbon::parse($this->start_time)->format('g:ia');
        $end = \Carbon\Carbon::parse($this->end_time)->format('g:ia');

        return "{$start} - {$end}";
    }

    public function getFormattedStartTimeAttribute(): string
    {
        return \Carbon\Carbon::parse($this->start_time)->format('g:ia');
    }

    public function getFormattedEndTimeAttribute(): string
    {
        return \Carbon\Carbon::parse($this->end_time)->format('g:ia');
    }

    protected static function booted(): void
    {
        static::creating(function (LiveTimeSlot $slot) {
            if (empty($slot->duration_minutes)) {
                $start = \Carbon\Carbon::parse($slot->start_time);
                $end = \Carbon\Carbon::parse($slot->end_time);
                $slot->duration_minutes = $start->diffInMinutes($end);
            }
        });
    }
}
