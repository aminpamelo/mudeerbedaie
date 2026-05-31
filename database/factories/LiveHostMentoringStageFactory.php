<?php

namespace Database\Factories;

use App\Models\LiveHostMentoringProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostMentoringStage>
 */
class LiveHostMentoringStageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => LiveHostMentoringProgram::factory(),
            'position' => $this->faker->numberBetween(1, 5),
            'name' => $this->faker->word(),
            'description' => null,
            'is_final' => false,
        ];
    }

    public function final(): static
    {
        return $this->state(fn () => ['is_final' => true]);
    }
}
