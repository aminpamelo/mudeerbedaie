<?php

namespace Database\Factories;

use App\Models\ClassModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassSyllabus>
 */
class ClassSyllabusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'sort_order' => 0,
        ];
    }

    public function for_class(ClassModel $class): static
    {
        return $this->state(fn () => ['class_id' => $class->id]);
    }
}
