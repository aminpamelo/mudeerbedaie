<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 50, 500);

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'price' => $price,
            'original_price' => $price * 1.2,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'status' => 'active',
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
