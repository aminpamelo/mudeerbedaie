<?php

namespace Database\Factories;

use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostMentee>
 */
class LiveHostMenteeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => LiveHostMentoringProgram::factory(),
            'mentee_user_id' => User::factory()->state(['role' => 'live_host']),
            'mentor_user_id' => null,
            'mentee_number' => 'LHM-'.now()->format('Ym').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'current_stage_id' => null,
            'status' => 'active',
            'level_id' => null,
            'level_source' => null,
            'level_assigned_at' => null,
            'level_assigned_by' => null,
            'rating' => null,
            'notes' => null,
            'enrolled_at' => now(),
            'graduated_at' => null,
        ];
    }
}
