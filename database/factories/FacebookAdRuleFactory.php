<?php

namespace Database\Factories;

use App\Models\FacebookAdAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FacebookAdRule>
 */
class FacebookAdRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facebook_ad_account_id' => FacebookAdAccount::factory(),
            'name' => fake()->words(3, true),
            'level' => 'adset',
            'metric' => 'fb_roas',
            'operator' => 'lt',
            'threshold' => 1.5,
            'min_spend' => 50,
            'lookback_days' => 3,
            'action' => 'pause',
            'action_value' => null,
            'cooldown_hours' => 24,
            'is_active' => true,
            'dry_run' => false,
        ];
    }

    public function dryRun(): static
    {
        return $this->state(fn () => ['dry_run' => true]);
    }
}
