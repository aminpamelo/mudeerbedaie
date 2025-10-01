<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlatformAccount>
 */
class PlatformAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_id' => \App\Models\Platform::factory(),
            'user_id' => \App\Models\User::factory(),
            'name' => fake()->company().' Account',
            'account_id' => fake()->uuid(),
            'seller_center_id' => fake()->uuid(),
            'business_manager_id' => fake()->uuid(),
            'shop_id' => fake()->randomNumber(8),
            'store_id' => fake()->randomNumber(8),
            'email' => fake()->email(),
            'phone' => fake()->phoneNumber(),
            'country_code' => fake()->countryCode(),
            'currency' => 'MYR',
            'description' => fake()->sentence(),
            'metadata' => [],
            'permissions' => ['orders', 'products'],
            'connected_at' => now(),
            'last_sync_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true,
            'auto_sync_orders' => false,
            'auto_sync_products' => false,
        ];
    }
}
