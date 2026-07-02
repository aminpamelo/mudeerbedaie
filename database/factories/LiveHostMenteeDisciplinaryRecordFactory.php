<?php

namespace Database\Factories;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDisciplinaryRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveHostMenteeDisciplinaryRecord>
 */
class LiveHostMenteeDisciplinaryRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentee_id' => LiveHostMentee::factory(),
            'incident_date' => $this->faker->dateTimeThisMonth()->format('Y-m-d'),
            'category' => $this->faker->randomElement(LiveHostMenteeDisciplinaryRecord::CATEGORIES),
            'severity' => $this->faker->randomElement(LiveHostMenteeDisciplinaryRecord::SEVERITIES),
            'description' => $this->faker->sentence(),
            'recorded_by' => User::factory(),
            'acknowledged_at' => null,
        ];
    }
}
