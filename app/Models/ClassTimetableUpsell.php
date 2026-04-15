<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTimetableUpsell extends Model
{
    protected $fillable = [
        'class_timetable_id',
        'day_of_week',
        'time_slot',
        'funnel_id',
        'pic_user_id',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(ClassTimetable::class, 'class_timetable_id');
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSlot($query, string $dayOfWeek, string $timeSlot)
    {
        return $query->where('day_of_week', $dayOfWeek)->where('time_slot', $timeSlot);
    }
}
