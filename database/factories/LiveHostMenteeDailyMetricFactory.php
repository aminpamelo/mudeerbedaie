<?php

namespace Database\Factories;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyMetric;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveHostMenteeDailyMetric>
 */
class LiveHostMenteeDailyMetricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentee_id' => LiveHostMentee::factory(),
            'metric_date' => $this->faker->dateTimeThisMonth()->format('Y-m-d'),
            'sales_override' => null,
            'comment' => $this->faker->sentence(),
            'commented_by' => User::factory(),
            'commented_at' => now(),
        ];
    }

    public function withOverride(float $amount): static
    {
        return $this->state(fn () => ['sales_override' => $amount]);
    }
}
