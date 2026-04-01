<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AttendanceLog extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceLogFactory> */
    use HasFactory;

    protected $appends = ['clock_in_photo_url', 'clock_out_photo_url'];

    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'clock_in_photo',
        'clock_out_photo',
        'clock_in_ip',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_ip',
        'clock_out_latitude',
        'clock_out_longitude',
        'status',
        'late_minutes',
        'early_leave_minutes',
        'total_work_minutes',
        'is_overtime',
        'remarks',
        'approved_by',
        'ot_claim_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'is_overtime' => 'boolean',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'total_work_minutes' => 'integer',
        ];
    }

    /**
     * Get the full URL for the clock-in photo.
     */
    public function getClockInPhotoUrlAttribute(): ?string
    {
        return $this->clock_in_photo ? Storage::disk('public')->url($this->clock_in_photo) : null;
    }

    /**
     * Get the full URL for the clock-out photo.
     */
    public function getClockOutPhotoUrlAttribute(): ?string
    {
        return $this->clock_out_photo ? Storage::disk('public')->url($this->clock_out_photo) : null;
    }

    /**
     * Get the employee for this attendance log.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who approved this attendance log.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the penalties associated with this attendance log.
     */
    public function penalties(): HasMany
    {
        return $this->hasMany(AttendancePenalty::class);
    }

    /**
     * Scope to filter attendance logs for a specific date.
     */
    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope to filter attendance logs for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter attendance logs by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
