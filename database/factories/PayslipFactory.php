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
        $periodStart = fake()->dateTimeBetween('-2 months', '-1 month');
        $periodEnd = (clone $periodStart)->modify('+1 month');

        return [
            'payslip_number' => 'PS-'.date('Ym').'-'.fake()->unique()->numberBetween(1000, 9999),
            'teacher_id' => Teacher::factory(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_sessions' => fake()->numberBetween(10, 30),
            'total_amount' => fake()->randomFloat(2, 1000, 5000),
            'status' => 'draft',
            'generated_at' => now(),
            'generated_by' => \App\Models\User::factory(),
            'approved_at' => null,
            'approved_by' => null,
            'paid_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => \App\Models\User::factory(),
        ]);
    }

    public function paid(): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
