<?php

namespace Database\Factories;

use App\Models\BlogIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogIssue>
 */
class BlogIssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->unique()->url();

        return [
            'title' => fake()->sentence(8),
            'summary' => fake()->paragraph(),
            'source' => 'Berita Harian',
            'url' => $url,
            'url_hash' => BlogIssue::hashUrl($url),
            'published_at' => now()->subHours(2),
            'relevance' => 8,
            'angle' => 'Kupasan dari sudut amanah dan tanggungjawab.',
            'status' => BlogIssue::STATUS_NEW,
        ];
    }
}
