<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalGross = fake()->randomFloat(2, 10000, 100000);
        $totalDeductions = $totalGross * fake()->randomFloat(2, 0.12, 0.18);
        $totalNet = $totalGross - $totalDeductions;

        return [
            'month' => fake()->numberBetween(1, 12),
            'year' => fake()->numberBetween(2024, 2026),
            'status' => 'draft',
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
            'total_employer_cost' => $totalGross + ($totalGross * 0.13),
            'employee_count' => fake()->numberBetween(1, 50),
            'prepared_by' => User::factory(),
            'reviewed_by' => null,
            'approved_by' => null,
            'approved_at' => null,
            'finalized_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Set as draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Set as review status.
     */
    public function review(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'review',
            'reviewed_by' => User::factory(),
        ]);
    }

    /**
     * Set as approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_by' => User::factory(),
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Set as finalized status.
     */
    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finalized',
            'reviewed_by' => User::factory(),
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(2),
            'finalized_at' => now(),
        ]);
    }
}
