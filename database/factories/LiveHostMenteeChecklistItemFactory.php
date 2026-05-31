<?php

namespace Database\Factories;

use App\Models\LiveHostMentee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostMenteeChecklistItem>
 */
class LiveHostMenteeChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentee_id' => LiveHostMentee::factory(),
            'title' => $this->faker->sentence(3),
            'description' => null,
            'is_required' => true,
            'status' => 'pending',
            'position' => $this->faker->numberBetween(0, 10),
            'due_at' => null,
            'completed_at' => null,
            'completed_by' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => 'done', 'completed_at' => now()]);
    }
}
