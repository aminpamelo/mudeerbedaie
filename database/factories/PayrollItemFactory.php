<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollItem>
 */
class PayrollItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['earning', 'deduction']);

        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employee_id' => Employee::factory(),
            'salary_component_id' => null,
            'component_code' => fake()->regexify('[A-Z]{3,6}'),
            'component_name' => fake()->randomElement(['Basic Salary', 'Housing Allowance', 'EPF (Employee)', 'SOCSO (Employee)', 'PCB / MTD']),
            'type' => $type,
            'amount' => fake()->randomFloat(2, 100, 8000),
            'is_statutory' => false,
        ];
    }

    /**
     * Set as an earning item.
     */
    public function earning(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'earning',
        ]);
    }

    /**
     * Set as a deduction item.
     */
    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deduction',
        ]);
    }

    /**
     * Set as a statutory deduction (EPF/SOCSO/EIS/PCB).
     */
    public function statutory(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deduction',
            'is_statutory' => true,
        ]);
    }
}
