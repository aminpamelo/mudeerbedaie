<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FunnelSession>
 */
class FunnelSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => \App\Models\Funnel::factory(),
            'visitor_id' => fake()->uuid(),
            'status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'converted',
            'converted_at' => now(),
        ]);
    }

    public function withAffiliate(int $affiliateId): static
    {
        return $this->state(fn (array $attributes) => [
            'affiliate_id' => $affiliateId,
        ]);
    }
}
