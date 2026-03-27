<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HrPayslip>
 */
class HrPayslipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grossSalary = fake()->randomFloat(2, 2000, 20000);
        $epfEmployee = round($grossSalary * 0.11);
        $socsoEmployee = fake()->randomFloat(2, 5, 80);
        $eisEmployee = round(min($grossSalary, 6000) * 0.002, 2);
        $pcbAmount = fake()->randomFloat(2, 0, 500);
        $totalDeductions = $epfEmployee + $socsoEmployee + $eisEmployee + $pcbAmount;
        $netSalary = $grossSalary - $totalDeductions;

        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employee_id' => Employee::factory(),
            'month' => fake()->numberBetween(1, 12),
            'year' => fake()->numberBetween(2024, 2026),
            'gross_salary' => $grossSalary,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'epf_employee' => $epfEmployee,
            'epf_employer' => round($grossSalary * 0.13),
            'socso_employee' => $socsoEmployee,
            'socso_employer' => fake()->randomFloat(2, 10, 150),
            'eis_employee' => $eisEmployee,
            'eis_employer' => $eisEmployee,
            'pcb_amount' => $pcbAmount,
            'unpaid_leave_days' => 0,
            'unpaid_leave_deduction' => 0,
            'pdf_path' => null,
        ];
    }

    /**
     * Set for a specific month/year.
     */
    public function forPeriod(int $month, int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'month' => $month,
            'year' => $year,
        ]);
    }
}
