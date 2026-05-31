<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostMentoringLevel>
 */
class LiveHostMentoringLevelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'color' => $this->faker->hexColor(),
            'position' => $this->faker->numberBetween(1, 5),
            'is_top' => false,
            'description' => null,
            'min_sessions' => null,
            'min_hours' => null,
            'min_gmv_myr' => null,
            'min_attendance_pct' => null,
            'is_active' => true,
        ];
    }

    public function top(): static
    {
        return $this->state(fn () => ['is_top' => true]);
    }
}
