<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stripe_customer_id' => null,
            'type' => 'stripe_card',
            'stripe_payment_method_id' => 'pm_'.$this->faker->lexify('????????????????'),
            'card_details' => [
                'brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex']),
                'last4' => $this->faker->numerify('####'),
                'exp_month' => $this->faker->numberBetween(1, 12),
                'exp_year' => $this->faker->numberBetween(2025, 2030),
                'funding' => 'credit',
                'country' => 'MY',
            ],
            'is_default' => false,
        ];
    }

    /**
     * Default payment method.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Visa card.
     */
    public function visa(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_details' => array_merge($attributes['card_details'] ?? [], [
                'brand' => 'visa',
            ]),
        ]);
    }

    /**
     * Mastercard.
     */
    public function mastercard(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_details' => array_merge($attributes['card_details'] ?? [], [
                'brand' => 'mastercard',
            ]),
        ]);
    }
}
