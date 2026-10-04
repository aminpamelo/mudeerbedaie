<?php

namespace Database\Factories;

use App\Models\CekbotMedia;
use App\Models\Media;
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
        return [
            'key' => 'testimoni-'.fake()->unique()->numberBetween(1, 99999),
            'media_id' => Media::factory()->state(['file_size' => 500_000]),
            'title' => 'Testimoni pelanggan',
            'description' => 'Hantar bila pelanggan ragu-ragu.',
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'media_id' => Media::factory()->video()->state(['file_size' => 2_000_000]),
        ]);
    }
}
