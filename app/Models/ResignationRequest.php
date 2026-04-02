<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResignationRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ResignationRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'submitted_date',
        'reason',
        'notice_period_days',
        'last_working_date',
        'requested_last_date',
        'status',
        'approved_by',
        'approved_at',
        'final_last_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_date' => 'date',
            'last_working_date' => 'date',
            'requested_last_date' => 'date',
            'final_last_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function exitChecklist(): HasOne
    {
        return $this->hasOne(ExitChecklist::class);
    }

    public function finalSettlement(): HasOne
    {
        return $this->hasOne(FinalSettlement::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public static function calculateNoticePeriod(Employee $employee): int
    {
        $employeeTypes = is_array($employee->employment_type) ? $employee->employment_type : [$employee->employment_type];

        if (in_array('probation', $employeeTypes)) {
            return 14;
        }

        if (count(array_intersect($employeeTypes, ['contract', 'intern'])) > 0) {
            return 30;
        }

        $yearsOfService = $employee->join_date->diffInYears(now());

        if ($yearsOfService < 2) {
            return 30;
        } elseif ($yearsOfService <= 5) {
            return 60;
        } else {
            return 90;
        }
    }
}
