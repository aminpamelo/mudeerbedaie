<?php

namespace App\Models;

use App\Services\Hr\StatutoryCalculationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalSettlement extends Model
{
    /** @use HasFactory<\Database\Factories\FinalSettlementFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'resignation_request_id',
        'prorated_salary',
        'leave_encashment',
        'leave_encashment_days',
        'other_earnings',
        'other_deductions',
        'epf_employee',
        'epf_employer',
        'socso_employee',
        'eis_employee',
        'pcb_amount',
        'total_gross',
        'total_deductions',
        'net_amount',
        'status',
        'notes',
        'pdf_path',
        'approved_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'prorated_salary' => 'decimal:2',
            'leave_encashment' => 'decimal:2',
            'leave_encashment_days' => 'decimal:1',
            'other_earnings' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'epf_employee' => 'decimal:2',
            'epf_employer' => 'decimal:2',
            'socso_employee' => 'decimal:2',
            'eis_employee' => 'decimal:2',
            'pcb_amount' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function resignationRequest(): BelongsTo
    {
        return $this->belongsTo(ResignationRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public static function calculate(int $employeeId, string $finalLastDate): self
    {
        $employee = Employee::with('salaries.salaryComponent')->findOrFail($employeeId);
        $statutory = app(StatutoryCalculationService::class);

        $totalMonthly = EmployeeSalary::forEmployee($employeeId)
            ->active()
            ->sum('amount');

        $basicSalary = EmployeeSalary::forEmployee($employeeId)
            ->active()
            ->whereHas('salaryComponent', fn ($q) => $q->where('is_basic', true))
            ->sum('amount');

        $finalDate = \Carbon\Carbon::parse($finalLastDate);
        $daysInMonth = $finalDate->daysInMonth;
        $daysWorked = $finalDate->day;
        $proratedSalary = ($totalMonthly / $daysInMonth) * $daysWorked;

        $annualLeaveType = LeaveType::where('code', 'AL')->first();
        $unusedDays = 0;

        if ($annualLeaveType) {
            $balance = LeaveBalance::where('employee_id', $employeeId)
                ->where('leave_type_id', $annualLeaveType->id)
                ->where('year', now()->year)
                ->first();

            $unusedDays = $balance ? (float) $balance->available_days : 0;
        }

        $dailyRate = $basicSalary / 26;
        $leaveEncashment = $unusedDays * $dailyRate;

        $totalGross = $proratedSalary + $leaveEncashment;

        $epfEmployee = $statutory->calculateEpfEmployee($proratedSalary);
        $epfEmployer = $statutory->calculateEpfEmployer($proratedSalary);
        $socsoEmployee = $statutory->calculateSocsoEmployee($proratedSalary);
        $eisEmployee = $statutory->calculateEisEmployee($proratedSalary);
        $pcbAmount = 0;

        $totalDeductions = $epfEmployee + $socsoEmployee + $eisEmployee + $pcbAmount;

        $netAmount = $totalGross - $totalDeductions;

        return new self([
            'employee_id' => $employeeId,
            'prorated_salary' => round($proratedSalary, 2),
            'leave_encashment' => round($leaveEncashment, 2),
            'leave_encashment_days' => $unusedDays,
            'other_earnings' => 0,
            'other_deductions' => 0,
            'epf_employee' => $epfEmployee,
            'epf_employer' => $epfEmployer,
            'socso_employee' => $socsoEmployee,
            'eis_employee' => $eisEmployee,
            'pcb_amount' => $pcbAmount,
            'total_gross' => round($totalGross, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_amount' => round($netAmount, 2),
            'status' => 'calculated',
        ]);
    }
}
