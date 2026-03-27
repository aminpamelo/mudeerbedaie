<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayslip extends Model
{
    /** @use HasFactory<\Database\Factories\HrPayslipFactory> */
    use HasFactory;

    protected $table = 'hr_payslips';

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'month', 'year',
        'gross_salary', 'total_deductions', 'net_salary',
        'epf_employee', 'epf_employer', 'socso_employee', 'socso_employer',
        'eis_employee', 'eis_employer', 'pcb_amount',
        'unpaid_leave_days', 'unpaid_leave_deduction', 'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'epf_employee' => 'decimal:2',
            'epf_employer' => 'decimal:2',
            'socso_employee' => 'decimal:2',
            'socso_employer' => 'decimal:2',
            'eis_employee' => 'decimal:2',
            'eis_employer' => 'decimal:2',
            'pcb_amount' => 'decimal:2',
            'unpaid_leave_deduction' => 'decimal:2',
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

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
