<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBenefit extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeBenefitFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'benefit_type_id',
        'provider',
        'policy_number',
        'coverage_amount',
        'employer_contribution',
        'employee_contribution',
        'start_date',
        'end_date',
        'notes',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coverage_amount' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
            'employee_contribution' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the employee this benefit belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the benefit type for this record.
     */
    public function benefitType(): BelongsTo
    {
        return $this->belongsTo(BenefitType::class);
    }

    /**
     * Scope to filter active employee benefits.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
