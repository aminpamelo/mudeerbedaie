<?php

namespace Database\Factories;

use App\Models\AiSalesPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiSalesPage>
 */
class AiSalesPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->catchPhrase();

        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'prompt' => $this->faker->paragraph(),
            'target_audience' => $this->faker->randomElement(['Small business owners', 'Students', 'Parents', 'Entrepreneurs']),
            'tone' => $this->faker->randomElement(['Professional', 'Friendly', 'Urgent', 'Playful']),
            'model' => 'gpt-4o',
            'html' => '<!DOCTYPE html><html><head><title>'.e($title).'</title></head><body><h1>'.e($title).'</h1></body></html>',
            'custom_css' => null,
            'custom_js' => null,
            'meta_title' => $title,
            'meta_description' => $this->faker->sentence(),
            'generation_status' => 'idle',
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'generation_status' => 'processing',
        ]);
    }
}
