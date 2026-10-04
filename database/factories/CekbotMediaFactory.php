<?php

namespace Database\Factories;

use App\Models\CekbotMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CekbotMedia>
 */
class CekbotMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'testimoni-'.fake()->unique()->numberBetween(1, 99999);

        return [
            'key' => $key,
            'title' => 'Testimoni pelanggan',
            'description' => 'Hantar bila pelanggan ragu-ragu.',
            'type' => CekbotMedia::TYPE_IMAGE,
            'path' => CekbotMedia::DIRECTORY."/{$key}.jpg",
            'mime' => 'image/jpeg',
            'size' => 1024,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CekbotMedia::TYPE_VIDEO,
            'path' => CekbotMedia::DIRECTORY."/{$attributes['key']}.mp4",
            'mime' => 'video/mp4',
        ]);
    }
}
