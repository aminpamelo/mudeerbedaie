<?php

namespace Database\Factories;

use App\Models\Platform;
use App\Models\PlatformApp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

class PlatformAppFactory extends Factory
{
    protected $model = PlatformApp::class;

    public function definition(): array
    {
        return [
            'platform_id' => Platform::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company().' App',
            'category' => PlatformApp::CATEGORY_MULTI_CHANNEL,
            'app_key' => fake()->uuid(),
            'encrypted_app_secret' => Crypt::encryptString(fake()->sha256()),
            'redirect_uri' => null,
            'scopes' => [],
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function analytics(): self
    {
        return $this->state(fn () => [
            'category' => PlatformApp::CATEGORY_ANALYTICS_REPORTING,
            'slug' => 'tiktok-analytics-reporting',
            'name' => 'TikTok Analytics & Reporting',
        ]);
    }

    public function multiChannel(): self
    {
        return $this->state(fn () => [
            'category' => PlatformApp::CATEGORY_MULTI_CHANNEL,
            'slug' => 'tiktok-multi-channel',
            'name' => 'TikTok Multi-Channel Management',
        ]);
    }
}
