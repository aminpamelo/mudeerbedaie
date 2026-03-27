<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePenalty extends Model
{
    /** @use HasFactory<\Database\Factories\AttendancePenaltyFactory> */
    use HasFactory;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'employee_id',
        'attendance_log_id',
        'penalty_type',
        'penalty_minutes',
        'month',
        'year',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'penalty_minutes' => 'integer',
            'month' => 'integer',
            'year' => 'integer',
        ];
    }

    /**
     * Get the employee who received this penalty.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the attendance log associated with this penalty.
     */
    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }
}
