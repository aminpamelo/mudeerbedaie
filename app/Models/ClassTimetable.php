<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

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
        if (!$this->weekly_schedule || empty($this->weekly_schedule)) {
            return [];
        }

        $sessions = [];
        $currentDate = $this->start_date->copy();
        $endDate = $this->end_date ?: $currentDate->copy()->addMonths(3);
        $sessionCount = 0;
        $maxSessions = $this->total_sessions ?: 50;

        while ($currentDate <= $endDate && $sessionCount < $maxSessions) {
            $dayOfWeek = strtolower($currentDate->format('l'));
            
            if (isset($this->weekly_schedule[$dayOfWeek])) {
                foreach ($this->weekly_schedule[$dayOfWeek] as $time) {
                    if ($sessionCount >= $maxSessions) break;
                    
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
            }

            if ($this->recurrence_pattern === 'bi_weekly' && $currentDate->dayOfWeek === 0) {
                $currentDate->addWeek();
            }
            
            $currentDate->addDay();
        }

        return $sessions;
    }

    public function getFormattedScheduleAttribute(): array
    {
        $formatted = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            if (isset($this->weekly_schedule[$day]) && !empty($this->weekly_schedule[$day])) {
                $formatted[ucfirst($day)] = $this->weekly_schedule[$day];
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
}
