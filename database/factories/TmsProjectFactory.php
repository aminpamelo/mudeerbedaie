<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TmsProject>
 */
class TmsProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'color' => fake()->hexColor(),
            'owner_id' => User::factory(),
            'status' => 'active',
        ];
    }

    public function onHold(): static
    {
        return $this->state(fn () => ['status' => 'on_hold']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
