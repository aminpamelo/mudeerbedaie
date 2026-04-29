<?php

declare(strict_types=1);

use App\Models\CmsContentPlatformPost;
use App\Models\CmsPlatform;
use App\Models\Content;
use App\Services\Cms\CreatePlatformPostsForContent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
});

it('creates one row per enabled platform', function () {
    $content = Content::factory()->create();

    app(CreatePlatformPostsForContent::class)->handle($content);

    expect(CmsContentPlatformPost::where('content_id', $content->id)->count())
        ->toBe(CmsPlatform::enabled()->count());
});

it('is idempotent — running twice does not duplicate rows', function () {
    $content = Content::factory()->create();
    $service = app(CreatePlatformPostsForContent::class);

    $service->handle($content);
    $service->handle($content);

    expect(CmsContentPlatformPost::where('content_id', $content->id)->count())
        ->toBe(CmsPlatform::enabled()->count());
});

it('ignores disabled platforms', function () {
    CmsPlatform::query()->where('key', 'threads')->update(['is_enabled' => false]);
    $content = Content::factory()->create();

    app(CreatePlatformPostsForContent::class)->handle($content);

    $platformIds = CmsContentPlatformPost::where('content_id', $content->id)
        ->pluck('platform_id')->toArray();

    expect($platformIds)
        ->not->toContain(CmsPlatform::where('key', 'threads')->value('id'));
});
