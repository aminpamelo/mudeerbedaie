<?php

namespace Database\Factories;

use App\Models\LiveHostPlatformCommissionRate;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostPlatformCommissionRate>
 */
class LiveHostPlatformCommissionRateFactory extends Factory
{
    protected $model = LiveHostPlatformCommissionRate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'live_host']),
            'platform_id' => Platform::factory(),
            'commission_rate_percent' => fake()->randomElement([3, 4, 5, 6]),
            'effective_from' => now(),
            'is_active' => true,
        ];
    }
}
