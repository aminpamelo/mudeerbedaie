<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Funnel>
 */
class FunnelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => fake()->sentence(3),
            'type' => 'sales',
            'status' => 'published',
            'settings' => [],
            'embed_settings' => [],
            'embed_enabled' => false,
            'affiliate_enabled' => false,
            'show_orders_in_admin' => true,
            'payment_settings' => [],
        ];
    }

    public function affiliateEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'affiliate_enabled' => true,
        ]);
    }

    public function hideOrdersFromAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_orders_in_admin' => false,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }
}
