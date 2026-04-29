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

it('returns a single platform post with relations', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->getJson("/api/cms/platform-posts/{$post->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonStructure(['data' => ['id', 'status', 'content', 'platform']]);
});
