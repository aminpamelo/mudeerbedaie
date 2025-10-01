<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Platform>
 */
class PlatformFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->slug(),
            'display_name' => fake()->company(),
            'description' => fake()->sentence(),
            'website_url' => fake()->url(),
            'api_base_url' => fake()->url(),
            'logo_url' => fake()->imageUrl(),
            'color_primary' => fake()->hexColor(),
            'color_secondary' => fake()->hexColor(),
            'type' => 'marketplace',
            'features' => ['orders', 'products'],
            'required_credentials' => ['api_key', 'api_secret'],
            'settings' => [],
            'is_active' => true,
            'supports_orders' => true,
            'supports_products' => true,
            'supports_webhooks' => true,
            'sort_order' => 0,
        ];
    }
}
