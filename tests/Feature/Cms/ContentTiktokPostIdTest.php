<?php

use App\Models\Content;

it('auto-populates tiktok_post_id when tiktok_url is set', function () {
    $content = Content::factory()->create([
        'tiktok_url' => 'https://www.tiktok.com/@shop/video/7452345678901234567',
        'tiktok_post_id' => null,
    ]);

    expect($content->fresh()->tiktok_post_id)->toBe('7452345678901234567');
});

it('leaves tiktok_post_id null when tiktok_url is missing', function () {
    $content = Content::factory()->create([
        'tiktok_url' => null,
    ]);

    expect($content->tiktok_post_id)->toBeNull();
});
