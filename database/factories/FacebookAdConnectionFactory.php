<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FacebookAdConnection>
 */
class FacebookAdConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'business_manager_id' => (string) fake()->numerify('############'),
            'access_token' => 'token-'.fake()->sha1(),
            'status' => 'connected',
            'can_manage_ads' => true,
            'permissions' => ['ads_read', 'ads_management'],
        ];
    }

    public function readOnly(): static
    {
        return $this->state(fn () => ['can_manage_ads' => false, 'permissions' => ['ads_read']]);
    }
}
