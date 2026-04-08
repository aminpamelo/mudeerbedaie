<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentApprover extends Model
{
    /** @use HasFactory<\Database\Factories\DepartmentApproverFactory> */
    use HasFactory;

    protected $fillable = [
        'department_id',
        'approver_employee_id',
        'approval_type',
        'tier',
    ];

    /**
     * Get the department for this approver assignment.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the employee who is the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    /**
     * Scope to filter by approval type.
     */
    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('approval_type', $type);
    }

    /**
     * Scope to filter by department.
     */
    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to filter by approval tier.
     */
    public function scopeForTier(Builder $query, int $tier): Builder
    {
        return $query->where('tier', $tier);
    }
}
