<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LandingPageTemplate>
 */
class LandingPageTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => 'sales',
            'content' => ['content' => [['type' => 'Heading', 'props' => ['id' => 'h1', 'text' => 'Hello']]], 'root' => ['props' => []]],
            'custom_css' => null,
            'is_active' => true,
        ];
    }
}
