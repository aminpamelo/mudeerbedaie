<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FunnelAutomation>
 */
class FunnelAutomationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'name' => fake()->sentence(3),
            'funnel_id' => null,
            'trigger_type' => 'purchase',
            'trigger_config' => [],
            'is_active' => true,
            'priority' => 0,
        ];
    }
}
