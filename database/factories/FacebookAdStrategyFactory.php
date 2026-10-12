<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FacebookAdStrategy>
 */
class FacebookAdStrategyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Strategy',
            'color' => 'orange',
            'description' => fake()->sentence(),
            'playbook' => "1. Launch\n2. Wait 3 days\n3. Cut losers",
            'launch_preset' => ['objective' => 'OUTCOME_SALES', 'daily_budget' => 50],
            'rule_presets' => [],
            'is_active' => true,
        ];
    }
}
