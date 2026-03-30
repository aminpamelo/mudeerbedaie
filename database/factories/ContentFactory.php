<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Content>
 */
class ContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'stage' => fake()->randomElement(['idea', 'shooting', 'editing', 'posting', 'posted']),
            'due_date' => fake()->dateTimeBetween('now', '+30 days'),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'created_by' => Employee::factory(),
        ];
    }

    /**
     * Set content as posted with TikTok URL
     */
    public function posted(): static
    {
        return $this->state(fn () => [
            'stage' => 'posted',
            'tiktok_url' => 'https://www.tiktok.com/@user/video/'.fake()->numerify('############'),
            'posted_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Set content as marked for ads
     */
    public function markedForAds(): static
    {
        return $this->state(fn () => [
            'is_marked_for_ads' => true,
            'marked_at' => now(),
        ]);
    }
}
