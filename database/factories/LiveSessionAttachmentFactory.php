<?php

namespace Database\Factories;

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveSessionAttachment>
 */
class LiveSessionAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->slug(2).'.jpg';

        return [
            'live_session_id' => LiveSession::factory(),
            'uploaded_by' => User::factory(),
            'file_name' => $name,
            'file_path' => "live-sessions/attachments/{$name}",
            'file_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(10_000, 2_000_000),
            'attachment_type' => null,
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Attachment flagged as a TikTok Shop backend screenshot (GMV proof).
     */
    public function tiktokShopScreenshot(): self
    {
        return $this->state(fn (): array => [
            'attachment_type' => \App\Models\LiveSessionAttachment::TYPE_TIKTOK_SHOP_SCREENSHOT,
            'file_type' => 'image/png',
        ]);
    }
}
