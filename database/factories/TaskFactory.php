<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'taskable_type' => Meeting::class,
            'taskable_id' => Meeting::factory(),
            'parent_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'assigned_to' => Employee::factory(),
            'assigned_by' => Employee::factory(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'pending',
            'deadline' => fake()->dateTimeBetween('now', '+30 days'),
            'completed_at' => null,
        ];
    }

    /**
     * Set status as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
