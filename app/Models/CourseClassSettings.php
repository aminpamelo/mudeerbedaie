<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseClassSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'teaching_mode',
        'billing_type',
        'sessions_per_month',
        'session_duration_minutes',
        'price_per_session',
        'price_per_month',
        'price_per_minute',
        'class_description',
        'class_instructions',
    ];

    protected function casts(): array
    {
        return [
            'sessions_per_month' => 'integer',
            'session_duration_minutes' => 'integer',
            'price_per_session' => 'decimal:2',
            'price_per_month' => 'decimal:2',
            'price_per_minute' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function getTeachingModeLabelAttribute(): string
    {
        return match ($this->teaching_mode) {
            'online' => 'Online',
            'offline' => 'Offline',
            'hybrid' => 'Hybrid',
            default => ucfirst($this->teaching_mode),
        };
    }

    public function getBillingTypeLabelAttribute(): string
    {
        return match ($this->billing_type) {
            'per_month' => 'Per Month',
            'per_session' => 'Per Session',
            'per_minute' => 'Per Minute',
            default => ucfirst(str_replace('_', ' ', $this->billing_type)),
        };
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->session_duration_minutes == 0) {
            return 'Not set';
        }

        $totalMinutes = $this->session_duration_minutes;
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        $duration = '';
        if ($hours > 0) {
            $duration .= $hours.'h ';
        }
        if ($minutes > 0) {
            $duration .= $minutes.'m';
        }

        return trim($duration) ?: $hours.'h';
    }

    public function getFormattedPriceAttribute(): string
    {
        return match ($this->billing_type) {
            'per_month' => $this->price_per_month ? 'RM '.number_format($this->price_per_month, 2) : 'RM 0.00',
            'per_session' => $this->price_per_session ? 'RM '.number_format($this->price_per_session, 2) : 'RM 0.00',
            'per_minute' => $this->price_per_minute ? 'RM '.number_format($this->price_per_minute, 2) : 'RM 0.00',
            default => 'RM 0.00',
        };
    }
}
