<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseFeeSettings>
 */
class CourseFeeSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'fee_amount' => $this->faker->randomFloat(2, 10, 500),
            'billing_cycle' => $this->faker->randomElement(['monthly', 'quarterly', 'yearly']),
            'currency' => 'MYR',
            'is_recurring' => true,
            'stripe_price_id' => null,
            'trial_period_days' => 0,
            'setup_fee' => 0,
        ];
    }

    /**
     * Monthly billing cycle.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_cycle' => 'monthly',
        ]);
    }

    /**
     * With trial period.
     */
    public function withTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_period_days' => $days,
        ]);
    }

    /**
     * With setup fee.
     */
    public function withSetupFee(float $amount = 25.00): static
    {
        return $this->state(fn (array $attributes) => [
            'setup_fee' => $amount,
        ]);
    }

    /**
     * With Stripe integration.
     */
    public function withStripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_price_id' => 'price_'.$this->faker->lexify('????????????????'),
        ]);
    }

    /**
     * One-time payment (non-recurring).
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => false,
            'billing_cycle' => null,
        ]);
    }
}
