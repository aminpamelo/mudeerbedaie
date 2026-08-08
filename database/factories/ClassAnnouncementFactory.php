<?php

namespace Database\Factories;

use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassAnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'author_id' => User::factory(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(2, true),
            'is_pinned' => false,
            'published_at' => now(),
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }
}
