<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payslip>
 */
class PayslipFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-2 months', '-1 month');
        $month = $date->format('Y-m');
        $year = (int) $date->format('Y');

        return [
            'teacher_id' => Teacher::factory(),
            'month' => $month,
            'year' => $year,
            'total_sessions' => fake()->numberBetween(10, 30),
            'total_amount' => fake()->randomFloat(2, 1000, 5000),
            'status' => 'draft',
            'generated_at' => now(),
            'generated_by' => \App\Models\User::factory(),
            'finalized_at' => null,
            'paid_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->finalized()->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
