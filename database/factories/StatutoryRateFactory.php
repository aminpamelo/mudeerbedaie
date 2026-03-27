<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StatutoryRate>
 */
class StatutoryRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minSalary = fake()->randomFloat(2, 0, 5000);

        return [
            'type' => fake()->randomElement(['epf_employee', 'epf_employer', 'socso_employee', 'socso_employer', 'eis_employee', 'eis_employer']),
            'min_salary' => $minSalary,
            'max_salary' => $minSalary + fake()->randomFloat(2, 500, 2000),
            'rate_percentage' => fake()->randomFloat(2, 0.5, 15),
            'fixed_amount' => fake()->optional(0.5)->randomFloat(2, 0.5, 100),
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
        ];
    }

    /**
     * Set as EPF employee rate.
     */
    public function epfEmployee(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'epf_employee',
            'rate_percentage' => 11.00,
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
        ]);
    }

    /**
     * Set as SOCSO employee rate with fixed amount.
     */
    public function socsoEmployee(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'socso_employee',
            'rate_percentage' => null,
            'fixed_amount' => fake()->randomFloat(2, 0.5, 70),
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
        ]);
    }

    /**
     * Set as expired (no longer current).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->subYears(2)->toDateString(),
            'effective_to' => now()->subYear()->toDateString(),
        ]);
    }
}
