<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 10, 500);
        $stripeFee = $amount * 0.029; // ~2.9% Stripe fee

        return [
            'enrollment_id' => Enrollment::factory(),
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'stripe_invoice_id' => 'in_'.$this->faker->lexify('????????????????'),
            'stripe_charge_id' => 'ch_'.$this->faker->lexify('????????????????'),
            'stripe_payment_intent_id' => 'pi_'.$this->faker->lexify('????????????????'),
            'amount' => $amount,
            'currency' => 'MYR',
            'status' => 'paid',
            'period_start' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'period_end' => $this->faker->dateTimeBetween('now', '+1 month'),
            'billing_reason' => 'subscription_cycle',
            'paid_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'failed_at' => null,
            'failure_reason' => null,
            'receipt_url' => 'https://pay.stripe.com/receipts/'.$this->faker->lexify('????????????????????'),
            'stripe_fee' => $stripeFee,
            'net_amount' => $amount - $stripeFee,
            'metadata' => [
                'system' => 'mudeer_bedaie',
                'created_by' => 'test',
            ],
        ];
    }

    /**
     * Pending order.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
            'stripe_charge_id' => null,
            'receipt_url' => null,
        ]);
    }

    /**
     * Failed order.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
            'failed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'failure_reason' => [
                'code' => $this->faker->randomElement(['card_declined', 'insufficient_funds', 'expired_card']),
                'message' => 'Payment failed - card declined',
            ],
        ]);
    }

    /**
     * Subscription creation order.
     */
    public function subscriptionCreate(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_reason' => 'subscription_create',
        ]);
    }

    /**
     * Subscription cycle order.
     */
    public function subscriptionCycle(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_reason' => 'subscription_cycle',
        ]);
    }

    /**
     * With specific amount.
     */
    public function withAmount(float $amount): static
    {
        $stripeFee = $amount * 0.029;

        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'stripe_fee' => $stripeFee,
            'net_amount' => $amount - $stripeFee,
        ]);
    }
}
