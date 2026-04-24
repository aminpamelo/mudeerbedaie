<?php

namespace Database\Factories;

use App\Models\LiveHostRecruitmentCampaign;
use App\Support\Recruitment\DefaultFormSchema;
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
        $email = $this->faker->unique()->safeEmail();

        return [
            'campaign_id' => LiveHostRecruitmentCampaign::factory(),
            'applicant_number' => 'LHA-'.now()->format('Ym').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'email' => $email,
            'form_data' => [
                'f_name' => $name,
                'f_email' => $email,
                'f_phone' => $this->faker->phoneNumber(),
                'f_platforms' => ['tiktok'],
            ],
            'form_schema_snapshot' => DefaultFormSchema::get(),
            'status' => 'active',
            'applied_at' => now(),
        ];
    }
}
