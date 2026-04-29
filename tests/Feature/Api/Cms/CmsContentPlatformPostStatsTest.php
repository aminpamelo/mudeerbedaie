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

it('merges stats keys without overwriting other keys', function () {
    $post = CmsContentPlatformPost::factory()->create([
        'stats' => ['views' => 100, 'likes' => 10],
    ]);

    $this->patchJson("/api/cms/platform-posts/{$post->id}/stats", [
        'comments' => 5,
    ])->assertSuccessful();

    expect($post->fresh()->stats)
        ->toMatchArray(['views' => 100, 'likes' => 10, 'comments' => 5]);
});

it('rejects negative numbers', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->patchJson("/api/cms/platform-posts/{$post->id}/stats", [
        'views' => -1,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['views']);
});
