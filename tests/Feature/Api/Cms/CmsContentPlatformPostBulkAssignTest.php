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

it('reassigns multiple posts to a single employee', function () {
    $assignee = Employee::factory()->create();
    $posts = CmsContentPlatformPost::factory()->count(3)->create();

    $response = $this->postJson('/api/cms/platform-posts/bulk-assign', [
        'post_ids' => $posts->pluck('id')->toArray(),
        'assignee_id' => $assignee->id,
    ]);

    $response->assertSuccessful();
    expect(CmsContentPlatformPost::whereIn('id', $posts->pluck('id'))
        ->where('assignee_id', $assignee->id)->count())->toBe(3);
});

it('allows clearing assignee with null', function () {
    $assignee = Employee::factory()->create();
    $posts = CmsContentPlatformPost::factory()->count(2)
        ->create(['assignee_id' => $assignee->id]);

    $this->postJson('/api/cms/platform-posts/bulk-assign', [
        'post_ids' => $posts->pluck('id')->toArray(),
        'assignee_id' => null,
    ])->assertSuccessful();

    expect(CmsContentPlatformPost::whereIn('id', $posts->pluck('id'))
        ->whereNull('assignee_id')->count())->toBe(2);
});
