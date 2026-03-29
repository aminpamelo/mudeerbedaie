<?php

namespace Database\Factories;

use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingProgram> */
class TrainingProgramFactory extends Factory
{
    protected $model = TrainingProgram::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+3 months');

        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['internal', 'external']),
            'category' => $this->faker->randomElement(['mandatory', 'technical', 'soft_skill', 'compliance']),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+1 day'),
            'max_participants' => $this->faker->numberBetween(10, 50),
            'cost_per_person' => $this->faker->randomFloat(2, 50, 500),
            'status' => 'planned',
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'start_date' => now()->subMonth(),
            'end_date' => now()->subMonth()->addDay(),
        ]);
    }
}
