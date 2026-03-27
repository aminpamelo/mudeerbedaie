<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    /** @use HasFactory<\Database\Factories\PayrollItemFactory> */
    use HasFactory;

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'salary_component_id',
        'component_code', 'component_name', 'type', 'amount', 'is_statutory',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_statutory' => 'boolean',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
