<?php

use App\Models\Content;
use App\Models\ContentStat;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    Employee::factory()->create([
        'user_id' => $this->user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $this->actingAs($this->user, 'sanctum');
});

// === Dashboard Stats ===

it('returns dashboard stats', function () {
    Content::factory()->count(3)->create();
    Content::factory()->posted()->create();

    $response = $this->getJson('/api/cms/dashboard/stats');

    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data)->toHaveKeys([
        'total_contents',
        'in_progress',
        'posted_this_month',
        'flagged_for_ads',
        'marked_for_ads',
        'by_stage',
    ]);
});

// === Top Posts ===

it('returns top posts', function () {
    $content = Content::factory()->posted()->create();
    ContentStat::create([
        'content_id' => $content->id,
        'views' => 5000,
        'likes' => 300,
        'comments' => 50,
        'shares' => 20,
        'fetched_at' => now(),
    ]);

    $response = $this->getJson('/api/cms/dashboard/top-posts');

    $response->assertSuccessful();
    expect($response->json('data'))->not->toBeEmpty();
});
