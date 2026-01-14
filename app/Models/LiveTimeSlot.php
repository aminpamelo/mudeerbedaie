<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveTimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_time',
        'end_time',
        'duration_minutes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
