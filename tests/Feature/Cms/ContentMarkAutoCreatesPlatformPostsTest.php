<?php

declare(strict_types=1);

use App\Models\CmsPlatform;
use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
});

it('auto-creates platform posts when content is marked', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);

    expect($content->platformPosts()->count())->toBe(0);

    $content->update(['is_marked_for_ads' => true, 'marked_at' => now()]);

    expect($content->fresh()->platformPosts()->count())
        ->toBe(CmsPlatform::enabled()->count());
});

it('does not create platform posts on unrelated updates', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);

    $content->update(['title' => 'New title']);

    expect($content->fresh()->platformPosts()->count())->toBe(0);
});

it('does not delete platform posts on unmark', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]);

    $content->update(['is_marked_for_ads' => false]);

    expect($content->fresh()->platformPosts()->count())
        ->toBe(CmsPlatform::enabled()->count());
});

it('is idempotent — re-marking does not duplicate', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]);
    $content->update(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]);

    expect($content->fresh()->platformPosts()->count())
        ->toBe(CmsPlatform::enabled()->count());
});
