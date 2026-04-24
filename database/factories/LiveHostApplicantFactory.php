<?php

namespace Database\Factories;

use App\Models\LiveHostRecruitmentCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostApplicant>
 */
class LiveHostApplicantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'campaign_id' => LiveHostRecruitmentCampaign::factory(),
            'applicant_number' => 'LHA-'.now()->format('Ym').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'platforms' => ['tiktok'],
            'experience_summary' => $this->faker->paragraph(),
            'motivation' => $this->faker->paragraph(),
            'status' => 'active',
            'applied_at' => now(),
        ];
    }
}
