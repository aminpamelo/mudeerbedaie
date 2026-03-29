<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeTaxProfile;
use App\Models\HrPayslip;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollProcessingService
{
    public function __construct(
        private StatutoryCalculationService $statutory
    ) {}

    /**
     * Calculate payroll for all active employees in a run.
     */
    public function calculateAll(PayrollRun $payrollRun): void
    {
        $employees = Employee::where('status', 'active')
            ->with(['activeSalaries.salaryComponent', 'taxProfile'])
            ->get();

        DB::transaction(function () use ($payrollRun, $employees) {
            // Clear existing items for this run
            $payrollRun->items()->delete();

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            $totalEmployerCost = 0;

            foreach ($employees as $employee) {
                $result = $this->calculateForEmployee($payrollRun, $employee);
                $totalGross += $result['gross'];
                $totalDeductions += $result['total_deductions'];
                $totalNet += $result['net'];
                $totalEmployerCost += $result['employer_cost'];
            }

            $payrollRun->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
                'total_employer_cost' => $totalEmployerCost,
                'employee_count' => $employees->count(),
            ]);
        });
    }

    /**
     * Calculate payroll for a single employee.
     *
     * @return array{gross: float, total_deductions: float, net: float, employer_cost: float}
     */
    public function calculateForEmployee(PayrollRun $payrollRun, Employee $employee): array
    {
        // Remove existing items for this employee in this run
        PayrollItem::where('payroll_run_id', $payrollRun->id)
            ->where('employee_id', $employee->id)
            ->where('is_statutory', true)
            ->delete();

        // 1. Get salary components
        $salaries = EmployeeSalary::forEmployee($employee->id)
            ->active()
            ->with('salaryComponent')
            ->get();

        $totalEarnings = 0;
        $epfApplicable = 0;
        $socsoApplicable = 0;
        $eisApplicable = 0;
        $basicSalary = 0;

        foreach ($salaries as $salary) {
            $component = $salary->salaryComponent;

            if ($component->type === 'earning') {
                // Create earning item (if not already exists as ad-hoc)
                $existing = PayrollItem::where('payroll_run_id', $payrollRun->id)
                    ->where('employee_id', $employee->id)
                    ->where('component_code', $component->code)
                    ->where('is_statutory', false)
                    ->first();

                if (! $existing) {
                    PayrollItem::create([
                        'payroll_run_id' => $payrollRun->id,
                        'employee_id' => $employee->id,
                        'salary_component_id' => $component->id,
                        'component_code' => $component->code,
                        'component_name' => $component->name,
                        'type' => 'earning',
                        'amount' => $salary->amount,
                        'is_statutory' => false,
                    ]);
                }

                $amount = $existing ? $existing->amount : $salary->amount;
                $totalEarnings += $amount;

                if ($component->code === 'BASIC') {
                    $basicSalary = $amount;
                }

                if ($component->is_epf_applicable) {
                    $epfApplicable += $amount;
                }

                if ($component->is_socso_applicable) {
                    $socsoApplicable += $amount;
                }

                if ($component->is_eis_applicable) {
                    $eisApplicable += $amount;
                }
            }
        }

        // 2. Calculate unpaid leave deduction
        $unpaidDays = $this->getUnpaidLeaveDays($employee->id, $payrollRun->month, $payrollRun->year);
        $divisor = (int) PayrollSetting::getValue('unpaid_leave_divisor', '26');
        $unpaidDeduction = $divisor > 0 ? ($basicSalary / $divisor) * $unpaidDays : 0;
        $unpaidDeduction = round($unpaidDeduction, 2);

        if ($unpaidDeduction > 0) {
            PayrollItem::create([
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $employee->id,
                'component_code' => 'UNPAID_LEAVE',
                'component_name' => "Unpaid Leave ({$unpaidDays} days)",
                'type' => 'deduction',
                'amount' => $unpaidDeduction,
                'is_statutory' => true,
            ]);
        }

        // 3. Gross = total earnings - unpaid leave deduction
        $gross = $totalEarnings - $unpaidDeduction;

        // Skip statutory calculations if no earnings
        if ($totalEarnings <= 0) {
            return [
                'gross' => 0,
                'total_deductions' => 0,
                'net' => 0,
                'employer_cost' => 0,
            ];
        }

        // 4. Calculate statutory deductions
        $taxProfile = $employee->taxProfile ?? new EmployeeTaxProfile([
            'marital_status' => 'single',
            'num_children' => 0,
            'num_children_studying' => 0,
            'disabled_individual' => false,
            'disabled_spouse' => false,
            'is_pcb_manual' => false,
        ]);

        $age = $employee->date_of_birth ? Carbon::parse($employee->date_of_birth)->age : 30;

        $statutory = $this->statutory->calculateAll(
            $epfApplicable - $unpaidDeduction,
            $socsoApplicable,
            $eisApplicable,
            $gross,
            $taxProfile,
            $age,
            $payrollRun->year
        );

        // Create statutory deduction items
        $statutoryItems = [
            ['code' => 'EPF_EE', 'name' => 'EPF (Employee)', 'type' => 'deduction', 'amount' => $statutory['epf_ee']],
            ['code' => 'EPF_ER', 'name' => 'EPF (Employer)', 'type' => 'employer_contribution', 'amount' => $statutory['epf_er']],
            ['code' => 'SOCSO_EE', 'name' => 'SOCSO (Employee)', 'type' => 'deduction', 'amount' => $statutory['socso_ee']],
            ['code' => 'SOCSO_ER', 'name' => 'SOCSO (Employer)', 'type' => 'employer_contribution', 'amount' => $statutory['socso_er']],
            ['code' => 'EIS_EE', 'name' => 'EIS (Employee)', 'type' => 'deduction', 'amount' => $statutory['eis_ee']],
            ['code' => 'EIS_ER', 'name' => 'EIS (Employer)', 'type' => 'employer_contribution', 'amount' => $statutory['eis_er']],
            ['code' => 'PCB', 'name' => 'PCB / MTD', 'type' => 'deduction', 'amount' => $statutory['pcb']],
        ];

        foreach ($statutoryItems as $item) {
            if ($item['amount'] > 0) {
                PayrollItem::create([
                    'payroll_run_id' => $payrollRun->id,
                    'employee_id' => $employee->id,
                    'component_code' => $item['code'],
                    'component_name' => $item['name'],
                    'type' => $item['type'],
                    'amount' => $item['amount'],
                    'is_statutory' => true,
                ]);
            }
        }

        // 5. Total deductions (employee portion only)
        $totalDeductions = $statutory['epf_ee'] + $statutory['socso_ee'] + $statutory['eis_ee'] + $statutory['pcb'] + $unpaidDeduction;

        // Add any non-statutory deduction items
        $adHocDeductions = PayrollItem::where('payroll_run_id', $payrollRun->id)
            ->where('employee_id', $employee->id)
            ->where('type', 'deduction')
            ->where('is_statutory', false)
            ->sum('amount');
        $totalDeductions += $adHocDeductions;

        // 6. Net = Gross - Total deductions
        $net = $gross - $totalDeductions;

        // 7. Employer cost = Gross + employer contributions
        $employerCost = $gross + $statutory['epf_er'] + $statutory['socso_er'] + $statutory['eis_er'];

        return [
            'gross' => $gross,
            'total_deductions' => $totalDeductions,
            'net' => $net,
            'employer_cost' => $employerCost,
        ];
    }

    /**
     * Generate payslip records for a finalized payroll run.
     */
    public function generatePayslips(PayrollRun $payrollRun): void
    {
        $employeeIds = $payrollRun->items()
            ->select('employee_id')
            ->distinct()
            ->pluck('employee_id');

        foreach ($employeeIds as $employeeId) {
            $items = $payrollRun->items()
                ->where('employee_id', $employeeId)
                ->get();

            $earnings = $items->where('type', 'earning')->sum('amount');
            $deductions = $items->where('type', 'deduction')->sum('amount');

            HrPayslip::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'month' => $payrollRun->month,
                    'year' => $payrollRun->year,
                ],
                [
                    'payroll_run_id' => $payrollRun->id,
                    'gross_salary' => $earnings,
                    'total_deductions' => $deductions,
                    'net_salary' => $earnings - $deductions,
                    'epf_employee' => $items->firstWhere('component_code', 'EPF_EE')?->amount ?? 0,
                    'epf_employer' => $items->firstWhere('component_code', 'EPF_ER')?->amount ?? 0,
                    'socso_employee' => $items->firstWhere('component_code', 'SOCSO_EE')?->amount ?? 0,
                    'socso_employer' => $items->firstWhere('component_code', 'SOCSO_ER')?->amount ?? 0,
                    'eis_employee' => $items->firstWhere('component_code', 'EIS_EE')?->amount ?? 0,
                    'eis_employer' => $items->firstWhere('component_code', 'EIS_ER')?->amount ?? 0,
                    'pcb_amount' => $items->firstWhere('component_code', 'PCB')?->amount ?? 0,
                    'unpaid_leave_days' => 0, // TODO: pull from items
                    'unpaid_leave_deduction' => $items->firstWhere('component_code', 'UNPAID_LEAVE')?->amount ?? 0,
                ]
            );
        }
    }

    /**
     * Get unpaid leave days for an employee in a given month.
     */
    private function getUnpaidLeaveDays(int $employeeId, int $month, int $year): float
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        return LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereHas('leaveType', function ($q) {
                $q->where('is_paid', false);
            })
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
            })
            ->sum('total_days');
    }
}
