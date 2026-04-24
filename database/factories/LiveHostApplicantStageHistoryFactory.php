<?php

namespace Database\Factories;

use App\Models\LiveHostApplicant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostApplicantStageHistory>
 */
class LiveHostApplicantStageHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applicant_id' => LiveHostApplicant::factory(),
            'from_stage_id' => null,
            'to_stage_id' => null,
            'action' => 'note',
            'notes' => $this->faker->sentence(),
            'changed_by' => null,
        ];
    }
}
