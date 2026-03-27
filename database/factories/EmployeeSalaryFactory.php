<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'salary_component_id' => SalaryComponent::factory(),
            'amount' => fake()->randomFloat(2, 500, 15000),
            'effective_from' => fake()->dateTimeBetween('-2 years', '-6 months')->format('Y-m-d'),
            'effective_to' => null,
        ];
    }

    /**
     * Set a specific basic salary amount.
     */
    public function basicSalary(float $amount = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
        ]);
    }

    /**
     * Set the salary as expired (ended).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => fake()->dateTimeBetween('-2 years', '-1 year')->format('Y-m-d'),
            'effective_to' => fake()->dateTimeBetween('-11 months', '-1 month')->format('Y-m-d'),
        ]);
    }

    /**
     * Set as currently active salary.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->subMonths(6)->toDateString(),
            'effective_to' => null,
        ]);
    }
}
