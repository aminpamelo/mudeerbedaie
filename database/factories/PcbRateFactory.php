<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PcbRate>
 */
class PcbRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minIncome = fake()->randomFloat(2, 1000, 10000);

        return [
            'category' => fake()->randomElement(['single', 'married_spouse_not_working', 'married_spouse_working']),
            'num_children' => fake()->numberBetween(0, 5),
            'min_monthly_income' => $minIncome,
            'max_monthly_income' => $minIncome + fake()->randomFloat(2, 500, 3000),
            'pcb_amount' => fake()->randomFloat(2, 0, 2000),
            'year' => fake()->numberBetween(2023, 2026),
        ];
    }

    /**
     * Set for a specific year.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => $year,
        ]);
    }

    /**
     * Set for single category.
     */
    public function single(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'single',
        ]);
    }
}
