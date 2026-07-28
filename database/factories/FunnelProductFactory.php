<?php

namespace Database\Factories;

use App\Models\FunnelProduct;
use App\Models\FunnelStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelProduct>
 */
class FunnelProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_step_id' => FunnelStep::factory(),
            'product_id' => null,
            'type' => 'main',
            'name' => fake()->words(3, true),
            'funnel_price' => fake()->randomFloat(2, 10, 500),
            'sort_order' => 0,
        ];
    }

    /**
     * Opt the funnel product in to external-system provisioning.
     */
    public function provisioning(int $externalSystemId, ?string $plan = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'settings' => [
                'provisioning' => array_filter([
                    'enabled' => true,
                    'external_system_id' => $externalSystemId,
                    'plan' => $plan,
                ], fn ($value): bool => $value !== null),
            ],
        ]);
    }
}
