<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveSession>
 */
class LiveSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_account_id' => \App\Models\PlatformAccount::factory(),
            'live_schedule_id' => null,
            'live_host_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'status' => 'scheduled',
            'scheduled_start_at' => now()->addDay(),
            'actual_start_at' => null,
            'actual_end_at' => null,
            'duration_minutes' => null,
        ];
    }
}
