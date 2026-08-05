<?php

namespace Database\Factories;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogComment>
 */
class BlogCommentFactory extends Factory
{
    protected $model = BlogComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'user_id' => User::factory(),
            'author_name' => $this->faker->name(),
            'parent_id' => null,
            'body' => $this->faker->paragraph(),
            'status' => BlogComment::STATUS_PENDING,
            'ip_address' => $this->faker->ipv4(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => BlogComment::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function spam(): static
    {
        return $this->state(fn () => ['status' => BlogComment::STATUS_SPAM]);
    }
}
