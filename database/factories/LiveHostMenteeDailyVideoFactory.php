<?php

namespace Database\Factories;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveHostMenteeDailyVideo>
 */
class LiveHostMenteeDailyVideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentee_id' => LiveHostMentee::factory(),
            'video_date' => $this->faker->dateTimeThisMonth()->format('Y-m-d'),
            'title' => $this->faker->sentence(4),
            'link' => $this->faker->optional()->url(),
            'logged_by' => User::factory(),
        ];
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['video_date' => $date]);
    }

    public function withoutLink(): static
    {
        return $this->state(fn () => ['link' => null]);
    }
}
