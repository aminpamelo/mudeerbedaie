<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->slug(3);
        $fileName = time().'_'.$name.'.jpg';

        return [
            'folder_id' => null,
            'title' => Str::title($this->faker->words(3, true)),
            'alt_text' => $this->faker->optional()->sentence(),
            'original_filename' => $name.'.jpg',
            'file_name' => $fileName,
            'file_path' => 'media/'.$fileName,
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'type' => 'image',
            'file_size' => $this->faker->numberBetween(50_000, 5_000_000),
            'width' => 1920,
            'height' => 1080,
            'duration' => null,
            'tags' => $this->faker->randomElements(['banner', 'product', 'hero', 'social', 'logo'], 2),
            'metadata' => null,
            'uploader_id' => null,
        ];
    }

    public function video(): static
    {
        return $this->state(function (array $attributes): array {
            $name = $this->faker->unique()->slug(3);
            $fileName = time().'_'.$name.'.mp4';

            return [
                'original_filename' => $name.'.mp4',
                'file_name' => $fileName,
                'file_path' => 'media/'.$fileName,
                'mime_type' => 'video/mp4',
                'type' => 'video',
                'width' => null,
                'height' => null,
                'duration' => $this->faker->numberBetween(10, 600),
                'file_size' => $this->faker->numberBetween(1_000_000, 20_000_000),
            ];
        });
    }
}
