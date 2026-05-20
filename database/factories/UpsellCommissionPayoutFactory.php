<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UpsellCommissionPayout>
 */
class UpsellCommissionPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_user_id' => User::factory(),
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'total_commission' => 100.00,
            'session_count' => 1,
            'status' => 'draft',
            'locked_at' => null,
            'paid_at' => null,
            'payment_reference' => null,
            'paid_by_user_id' => null,
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => 'locked',
            'locked_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'locked_at' => now()->subDay(),
            'paid_at' => now(),
            'payment_reference' => 'TXN-'.fake()->unique()->numerify('######'),
            'paid_by_user_id' => User::factory(),
        ]);
    }
}
