<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskChecklist>
 */
class TaskChecklistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => fake()->sentence(4),
            'is_completed' => false,
            'sort_order' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}
