<?php

namespace Database\Factories;

use App\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveAnalytics>
 */
class LiveAnalyticsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $peak = fake()->numberBetween(50, 5000);

        return [
            'live_session_id' => LiveSession::factory(),
            'viewers_peak' => $peak,
            'viewers_avg' => (int) round($peak * 0.6),
            'total_likes' => fake()->numberBetween(0, 2000),
            'total_comments' => fake()->numberBetween(0, 500),
            'total_shares' => fake()->numberBetween(0, 200),
            'gifts_value' => fake()->randomFloat(2, 0, 500),
            'duration_minutes' => fake()->numberBetween(30, 240),
        ];
    }
}
