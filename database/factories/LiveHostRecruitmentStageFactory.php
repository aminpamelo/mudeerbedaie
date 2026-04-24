<?php

namespace Database\Factories;

use App\Models\LiveHostRecruitmentCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostRecruitmentStage>
 */
class LiveHostRecruitmentStageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => LiveHostRecruitmentCampaign::factory(),
            'position' => 1,
            'name' => $this->faker->randomElement(['Review', 'Interview', 'Test Live', 'Final']),
            'description' => null,
            'is_final' => false,
        ];
    }
}
