<?php

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelAnalytics;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelAnalytics>
 */
class FunnelAnalyticsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'funnel_step_id' => null,
            'date' => now()->toDateString(),
            'unique_visitors' => $this->faker->numberBetween(0, 500),
            'pageviews' => $this->faker->numberBetween(0, 1000),
            'conversions' => $this->faker->numberBetween(0, 50),
            'revenue' => $this->faker->randomFloat(2, 0, 5000),
            'avg_time_seconds' => $this->faker->numberBetween(0, 300),
            'bounce_count' => $this->faker->numberBetween(0, 200),
        ];
    }
}
