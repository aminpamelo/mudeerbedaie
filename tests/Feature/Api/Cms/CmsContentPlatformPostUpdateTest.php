<?php

use App\Models\CmsContentPlatformPost;
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

it('updates status, post_url, posted_at, assignee_id', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->patchJson("/api/cms/platform-posts/{$post->id}", [
        'status' => 'posted',
        'post_url' => 'https://instagram.com/p/abc123',
        'posted_at' => '2026-04-30 12:00:00',
        'assignee_id' => $this->employee->id,
    ]);

    $response->assertSuccessful();
    expect($post->fresh())
        ->status->toBe('posted')
        ->post_url->toBe('https://instagram.com/p/abc123')
        ->assignee_id->toBe($this->employee->id);
});

it('rejects invalid status', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->patchJson("/api/cms/platform-posts/{$post->id}", [
        'status' => 'banana',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

it('rejects malformed post_url', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->patchJson("/api/cms/platform-posts/{$post->id}", [
        'post_url' => 'not a url',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['post_url']);
});
