<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosSale>
 */
class PosSaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 500);

        return [
            'sale_number' => 'POS-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_email' => fake()->safeEmail(),
            'customer_address' => fake()->optional()->address(),
            'salesperson_id' => User::factory(),
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'discount_type' => null,
            'total_amount' => $subtotal,
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer']),
            'payment_reference' => null,
            'payment_status' => 'paid',
            'notes' => null,
            'sale_date' => now(),
        ];
    }

    public function withCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => User::factory()->state(['role' => 'student']),
            'customer_name' => null,
            'customer_phone' => null,
            'customer_email' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'pending',
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'bank_transfer',
            'payment_reference' => fake()->uuid(),
        ]);
    }

    public function withDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $discountAmount = round($attributes['subtotal'] * 0.1, 2);

            return [
                'discount_amount' => $discountAmount,
                'discount_type' => 'percentage',
                'total_amount' => $attributes['subtotal'] - $discountAmount,
            ];
        });
    }
}
