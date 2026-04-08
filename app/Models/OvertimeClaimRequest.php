<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OvertimeClaimRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'claim_date',
        'start_time',
        'duration_minutes',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
        'attendance_id',
        'current_approval_tier',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Get the approval logs for this overtime claim request.
     */
    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }
}
