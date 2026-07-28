<?php

namespace Database\Factories;

use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\ProductOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExternalProvisioningRequest>
 */
class ExternalProvisioningRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_system_id' => ExternalSystem::factory(),
            'product_order_id' => ProductOrder::factory(),
            'funnel_product_id' => null,
            'idempotency_key' => 'prov_'.Str::random(24),
            'status' => ExternalProvisioningRequest::STATUS_PENDING,
            'attempts' => 0,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ExternalProvisioningRequest::STATUS_SUCCEEDED,
            'external_user_id' => (string) $this->faker->numberBetween(1, 9999),
            'login_url' => 'https://example.test/login',
            'provisioned_at' => now(),
        ]);
    }
}
