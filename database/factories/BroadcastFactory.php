<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Broadcast>
 */
class BroadcastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Campaign',
            'type' => fake()->randomElement(['standard', 'ab_test']),
            'status' => 'draft',
            'from_name' => fake()->name(),
            'from_email' => fake()->safeEmail(),
            'reply_to_email' => fake()->optional()->safeEmail(),
            'subject' => fake()->sentence(),
            'preview_text' => fake()->optional()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'scheduled_at' => null,
            'sent_at' => null,
            'total_recipients' => 0,
            'total_sent' => 0,
            'total_failed' => 0,
            'selected_students' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_at' => fake()->dateTimeBetween('now', '+7 days'),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }
}
