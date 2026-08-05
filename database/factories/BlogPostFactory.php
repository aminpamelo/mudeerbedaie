<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(6), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'excerpt' => $this->faker->paragraph(2),
            'content' => collect(range(1, 4))
                ->map(fn (int $i) => '## '.rtrim($this->faker->sentence(4), '.')."\n\n".$this->faker->paragraphs(2, true))
                ->implode("\n\n"),
            'category_id' => BlogCategory::factory(),
            'author_id' => User::factory(),
            'locale' => 'en',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDays($this->faker->numberBetween(0, 60)),
            'view_count' => $this->faker->numberBetween(0, 5000),
            'is_featured' => false,
            'allow_comments' => true,
            'meta_description' => $this->faker->text(150),
            'focus_keyword' => $this->faker->words(2, true),
            'noindex' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => BlogPost::STATUS_SCHEDULED,
            'published_at' => now()->addDays(3),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function malay(): static
    {
        return $this->state(fn () => ['locale' => 'ms']);
    }
}
