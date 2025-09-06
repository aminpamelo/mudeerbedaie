<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StripeCustomer>
 */
class StripeCustomerFactory extends Factory
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
            'stripe_customer_id' => 'cus_'.$this->faker->lexify('????????????????'),
            'stripe_data' => [
                'email' => $this->faker->email(),
                'name' => $this->faker->name(),
                'created' => now()->timestamp,
                'phone' => $this->faker->phoneNumber(),
                'default_source' => null,
                'default_payment_method' => null,
            ],
        ];
    }

    /**
     * With payment method.
     */
    public function withPaymentMethod(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_data' => array_merge($attributes['stripe_data'] ?? [], [
                'default_payment_method' => 'pm_'.$this->faker->lexify('????????????????'),
            ]),
        ]);
    }
}
