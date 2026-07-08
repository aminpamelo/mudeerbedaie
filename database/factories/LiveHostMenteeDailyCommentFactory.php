<?php

namespace Database\Factories;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveHostMenteeDailyComment>
 */
class LiveHostMenteeDailyCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentee_id' => LiveHostMentee::factory(),
            'metric_date' => $this->faker->dateTimeThisMonth()->format('Y-m-d'),
            'user_id' => User::factory(['role' => 'admin_livehost']),
            'comment' => $this->faker->sentence(),
        ];
    }
}
