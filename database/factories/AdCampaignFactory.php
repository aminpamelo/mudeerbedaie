<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdCampaign>
 */
class AdCampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_id' => Content::factory()->posted()->markedForAds(),
            'platform' => fake()->randomElement(['facebook', 'tiktok']),
            'status' => fake()->randomElement(['pending', 'running', 'paused', 'completed']),
            'budget' => fake()->randomFloat(2, 50, 5000),
            'start_date' => fake()->dateTimeBetween('now', '+7 days'),
            'end_date' => fake()->dateTimeBetween('+7 days', '+30 days'),
            'assigned_by' => Employee::factory(),
        ];
    }
}
