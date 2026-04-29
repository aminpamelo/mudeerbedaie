<?php

use App\Models\CmsContentPlatformPost;
use App\Models\CmsPlatform;
use App\Models\Content;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
    $this->user = User::factory()->create(['role' => 'admin']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $this->actingAs($this->user, 'sanctum');
});

it('lists platform posts', function () {
    CmsContentPlatformPost::factory()->count(3)->create();

    $response = $this->getJson('/api/cms/platform-posts');

    $response->assertSuccessful()->assertJsonCount(3, 'data');
});

it('filters by status', function () {
    CmsContentPlatformPost::factory()->posted()->create();
    CmsContentPlatformPost::factory()->create(); // pending

    $response = $this->getJson('/api/cms/platform-posts?status=posted');

    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

it('filters by platform_id', function () {
    $instagram = CmsPlatform::where('key', 'instagram')->first();
    $youtube = CmsPlatform::where('key', 'youtube')->first();
    CmsContentPlatformPost::factory()->create(['platform_id' => $instagram->id]);
    CmsContentPlatformPost::factory()->create(['platform_id' => $youtube->id]);

    $response = $this->getJson("/api/cms/platform-posts?platform_id={$instagram->id}");

    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

it('searches by content title', function () {
    $matching = Content::factory()->create(['title' => 'Buku Solat Hook']);
    $other = Content::factory()->create(['title' => 'Random Title']);
    CmsContentPlatformPost::factory()->create(['content_id' => $matching->id]);
    CmsContentPlatformPost::factory()->create(['content_id' => $other->id]);

    $response = $this->getJson('/api/cms/platform-posts?search=Solat');

    $response->assertSuccessful()->assertJsonCount(1, 'data');
});
