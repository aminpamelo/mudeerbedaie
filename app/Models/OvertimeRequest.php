<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OvertimeRequest extends Model
{
    /** @use HasFactory<\Database\Factories\OvertimeRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'requested_date',
        'start_time',
        'end_time',
        'estimated_hours',
        'actual_hours',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'replacement_hours_earned',
        'replacement_hours_used',
        'current_approval_tier',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'approved_at' => 'datetime',
            'estimated_hours' => 'decimal:1',
            'actual_hours' => 'decimal:1',
            'replacement_hours_earned' => 'decimal:1',
            'replacement_hours_used' => 'decimal:1',
        ];
    }

    /**
     * Get the employee who requested overtime.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who approved this overtime request.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope to filter pending overtime requests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter approved overtime requests.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to filter completed overtime requests.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get the replacement leave balance (earned minus used).
     */
    public function getReplacementBalanceAttribute(): float
    {
        return (float) $this->replacement_hours_earned - (float) $this->replacement_hours_used;
    }

    /**
     * Get the approval logs for this overtime request.
     */
    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }
}
