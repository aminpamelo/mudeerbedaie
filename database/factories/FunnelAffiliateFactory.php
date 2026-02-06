<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FunnelAffiliate>
 */
class FunnelAffiliateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('+601#########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'banned',
        ]);
    }
}
