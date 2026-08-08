<?php

namespace Database\Factories;

use App\Models\ClassModel;
use App\Models\ClassResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassResource>
 */
class ClassResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'session_id' => null,
            'uploaded_by' => User::factory(),
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(['recording', 'pdf', 'audio', 'link', 'note']),
            'file_path' => null,
            'url' => fake()->url(),
            'content' => null,
            'sort_order' => 0,
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false, 'published_at' => null]);
    }

    public function scheduledRelease(): static
    {
        return $this->state(fn () => ['is_published' => true, 'published_at' => now()->addDay()]);
    }

    public function note(): static
    {
        return $this->state(fn () => ['type' => 'note', 'url' => null, 'content' => fake()->paragraphs(3, true)]);
    }

    public function recording(): static
    {
        return $this->state(fn () => ['type' => 'recording', 'url' => 'https://youtube.com/watch?v='.fake()->regexify('[A-Za-z0-9]{11}')]);
    }
}
