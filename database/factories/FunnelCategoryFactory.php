<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FunnelCategory>
 */
class FunnelCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $palette = ['zinc', 'blue', 'emerald', 'amber', 'rose', 'violet', 'sky', 'orange'];

        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement($palette),
            'sort_order' => 0,
        ];
    }
}
