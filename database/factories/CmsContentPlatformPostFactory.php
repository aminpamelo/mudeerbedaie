<?php

namespace Database\Factories;

use App\Models\CmsContentPlatformPost;
use App\Models\CmsPlatform;
use App\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsContentPlatformPost>
 */
class CmsContentPlatformPostFactory extends Factory
{
    protected $model = CmsContentPlatformPost::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'platform_id' => CmsPlatform::factory(),
            'status' => 'pending',
            'post_url' => null,
            'posted_at' => null,
            'assignee_id' => null,
            'stats' => null,
        ];
    }

    public function posted(): self
    {
        return $this->state(fn () => [
            'status' => 'posted',
            'post_url' => $this->faker->url(),
            'posted_at' => now(),
        ]);
    }

    public function skipped(): self
    {
        return $this->state(fn () => ['status' => 'skipped']);
    }
}
