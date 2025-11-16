<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassSession>
 */
class ClassSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => \App\Models\ClassModel::factory(),
            'session_date' => fake()->dateTimeBetween('now', '+3 months'),
            'session_time' => fake()->time(),
            'duration_minutes' => fake()->randomElement([60, 90, 120, 150]),
            'status' => 'scheduled',
            'teacher_notes' => fake()->optional()->sentence(),
            'completed_at' => null,
            'started_at' => null,
            'allowance_amount' => null,
            'verified_at' => null,
            'verified_by' => null,
            'verifier_role' => null,
            'payout_status' => 'unpaid',
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'completed_at' => null,
            'started_at' => null,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ongoing',
            'started_at' => now()->subMinutes(fake()->numberBetween(10, 60)),
        ]);
    }

    public function completed(): static
    {
        $startedAt = now()->subDays(fake()->numberBetween(1, 30));
        $completedAt = $startedAt->copy()->addMinutes(fake()->numberBetween(60, 150));

        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'allowance_amount' => fake()->randomFloat(2, 50, 300),
        ]);
    }

    public function verified(): static
    {
        return $this->completed()->state(fn (array $attributes) => [
            'verified_at' => now(),
            'verified_by' => \App\Models\User::factory(),
            'verifier_role' => 'admin',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_show',
        ]);
    }
}
