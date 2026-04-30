<?php

declare(strict_types=1);

use App\Models\CmsContentPlatformPost;
use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
});

it('deletes platform posts when content is soft-deleted', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]);

    expect($content->platformPosts()->count())->toBeGreaterThan(0);

    $content->delete(); // soft delete

    expect(CmsContentPlatformPost::where('content_id', $content->id)->count())
        ->toBe(0);
});

it('recreates platform posts when a marked content is restored', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]);

    $expectedCount = $content->platformPosts()->count();

    $content->delete();
    expect(CmsContentPlatformPost::where('content_id', $content->id)->count())->toBe(0);

    $content->restore();

    expect(CmsContentPlatformPost::where('content_id', $content->id)->count())
        ->toBe($expectedCount);
});

it('does not recreate platform posts when an unmarked content is restored', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);

    $content->delete();
    $content->restore();

    expect(CmsContentPlatformPost::where('content_id', $content->id)->count())->toBe(0);
});

it('hides orphan platform posts from the index endpoint', function () {
    $userClass = \App\Models\User::class;
    $user = $userClass::factory()->create(['role' => 'admin']);
    $department = \App\Models\Department::factory()->create();
    $position = \App\Models\Position::factory()->create(['department_id' => $department->id]);
    \App\Models\Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $this->actingAs($user, 'sanctum');

    $a = Content::factory()->create(['is_marked_for_ads' => false]);
    $a->update(['is_marked_for_ads' => true]);
    $b = Content::factory()->create(['is_marked_for_ads' => false]);
    $b->update(['is_marked_for_ads' => true]);

    // Force-restore-ish: simulate an orphan by directly removing the content
    // without firing the observer, so the observer cleanup doesn't run.
    Content::withoutEvents(function () use ($a) {
        $a->delete();
    });

    $response = $this->getJson('/api/cms/platform-posts');

    $response->assertSuccessful();
    $contentIds = collect($response->json('data'))->pluck('content_id')->unique();

    expect($contentIds)->not->toContain($a->id);
    expect($contentIds)->toContain($b->id);
});
