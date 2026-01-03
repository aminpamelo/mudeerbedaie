<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTimetable extends Model
{
    protected $fillable = [
        'class_id',
        'weekly_schedule',
        'recurrence_pattern',
        'start_date',
        'end_date',
        'total_sessions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weekly_schedule' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function generateSessions(): array
    {
        if (! $this->weekly_schedule || empty($this->weekly_schedule)) {
            return [];
        }

        $sessions = [];
        $currentDate = $this->start_date->copy();
        $endDate = $this->end_date ?: $currentDate->copy()->addMonths(3);
        $sessionCount = 0;
        $maxSessions = $this->total_sessions ?: 50;

        while ($currentDate <= $endDate && $sessionCount < $maxSessions) {
            $dayOfWeek = strtolower($currentDate->format('l'));
            $timesForDay = [];

            if ($this->recurrence_pattern === 'monthly') {
                // For monthly, get the week number within the month (1-4)
                $weekOfMonth = $this->getWeekOfMonth($currentDate);
                $weekKey = 'week_'.$weekOfMonth;

                if (isset($this->weekly_schedule[$weekKey][$dayOfWeek])) {
                    $timesForDay = $this->weekly_schedule[$weekKey][$dayOfWeek];
                }
            } else {
                // For weekly and bi-weekly, use the standard structure
                if (isset($this->weekly_schedule[$dayOfWeek])) {
                    $timesForDay = $this->weekly_schedule[$dayOfWeek];
                }
            }

            foreach ($timesForDay as $time) {
                if ($sessionCount >= $maxSessions) {
                    break;
                }

                $sessions[] = [
                    'class_id' => $this->class_id,
                    'session_date' => $currentDate->toDateString(),
                    'session_time' => $time,
                    'duration_minutes' => $this->class->duration_minutes ?? 60,
                    'status' => 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $sessionCount++;
            }

            if ($this->recurrence_pattern === 'bi_weekly' && $currentDate->dayOfWeek === 0) {
                $currentDate->addWeek();
            }

            $currentDate->addDay();
        }

        return $sessions;
    }

    /**
     * Get the week number within a month (1-4).
     * Week 1: Days 1-7, Week 2: Days 8-14, Week 3: Days 15-21, Week 4: Days 22-31
     */
    public function getWeekOfMonth(\Carbon\Carbon $date): int
    {
        $dayOfMonth = $date->day;

        if ($dayOfMonth <= 7) {
            return 1;
        } elseif ($dayOfMonth <= 14) {
            return 2;
        } elseif ($dayOfMonth <= 21) {
            return 3;
        } else {
            return 4;
        }
    }

    public function getFormattedScheduleAttribute(): array
    {
        $formatted = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        if ($this->recurrence_pattern === 'monthly') {
            // For monthly, format each week separately
            for ($week = 1; $week <= 4; $week++) {
                $weekKey = 'week_'.$week;
                $weekSchedule = [];

                foreach ($days as $day) {
                    if (isset($this->weekly_schedule[$weekKey][$day]) && ! empty($this->weekly_schedule[$weekKey][$day])) {
                        $weekSchedule[ucfirst($day)] = $this->weekly_schedule[$weekKey][$day];
                    }
                }

                if (! empty($weekSchedule)) {
                    $formatted['Week '.$week] = $weekSchedule;
                }
            }
        } else {
            // For weekly and bi-weekly
            foreach ($days as $day) {
                if (isset($this->weekly_schedule[$day]) && ! empty($this->weekly_schedule[$day])) {
                    $formatted[ucfirst($day)] = $this->weekly_schedule[$day];
                }
            }
        }

        return $formatted;
    }

    public function getTotalScheduledSessionsAttribute(): int
    {
        return $this->class->sessions()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if a given date is within the timetable's valid date range.
     */
    public function isDateWithinRange(\Carbon\Carbon $date): bool
    {
        // Check start date
        if ($this->start_date && $date->lt($this->start_date->startOfDay())) {
            return false;
        }

        // Check end date
        if ($this->end_date && $date->gt($this->end_date->endOfDay())) {
            return false;
        }

        return true;
    }
}
