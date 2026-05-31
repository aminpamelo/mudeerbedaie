<?php

namespace Database\Factories;

use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostMentoringActivity>
 */
class LiveHostMentoringActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => LiveHostMentoringProgram::factory(),
            'leader_user_id' => User::factory()->state(['role' => 'live_host']),
            'mentee_id' => null,
            'type' => $this->faker->randomElement(['coaching', 'meeting', 'training', 'check_in', 'other']),
            'title' => $this->faker->sentence(3),
            'notes' => $this->faker->optional()->paragraph(),
            'occurred_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
