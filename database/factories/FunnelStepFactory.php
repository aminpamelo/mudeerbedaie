<?php

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FunnelStep>
 */
class FunnelStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'name' => fake()->words(2, true),
            'slug' => 'step-'.Str::lower(Str::random(10)),
            'type' => 'checkout',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
