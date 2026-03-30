<?php

use App\Models\Content;
use App\Models\ContentStage;
use App\Models\ContentStat;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAdminWithEmployee(): array
{
    $user = User::factory()->create(['role' => 'admin']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    return [$user, $employee];
}

beforeEach(function () {
    [$this->user, $this->employee] = createAdminWithEmployee();
    $this->actingAs($this->user, 'sanctum');
});

// === List Contents ===

it('can list contents', function () {
    Content::factory()->count(3)->create(['created_by' => $this->employee->id]);

    $response = $this->getJson('/api/cms/contents');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(3);
});

// === Create Content ===

it('can create content with stages', function () {
    $secondEmployee = Employee::factory()->create();

    $response = $this->postJson('/api/cms/contents', [
        'title' => 'New Video Content',
        'description' => 'A test description',
        'priority' => 'high',
        'stages' => [
            [
                'stage' => 'idea',
                'assignees' => [
                    ['employee_id' => $this->employee->id, 'role' => 'creator'],
                ],
            ],
            [
                'stage' => 'shooting',
                'assignees' => [
                    ['employee_id' => $secondEmployee->id, 'role' => 'camera'],
                ],
            ],
        ],
    ]);

    $response->assertCreated();
    expect(Content::where('title', 'New Video Content')->exists())->toBeTrue();
    expect(ContentStage::where('content_id', $response->json('data.id'))->count())->toBe(4);
});

it('requires title to create content', function () {
    $response = $this->postJson('/api/cms/contents', [
        'priority' => 'high',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

// === Show Content ===

it('can show content detail', function () {
    $content = Content::factory()->create([
        'title' => 'Detail Test Content',
        'created_by' => $this->employee->id,
    ]);

    $response = $this->getJson("/api/cms/contents/{$content->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.title', 'Detail Test Content');
});

// === Update Content ===

it('can update content', function () {
    $content = Content::factory()->create(['created_by' => $this->employee->id]);

    $response = $this->putJson("/api/cms/contents/{$content->id}", [
        'title' => 'Updated Title',
        'priority' => 'urgent',
    ]);

    $response->assertSuccessful();
    expect($content->fresh()->title)->toBe('Updated Title');
});

// === Delete Content ===

it('can delete content', function () {
    $content = Content::factory()->create(['created_by' => $this->employee->id]);

    $response = $this->deleteJson("/api/cms/contents/{$content->id}");

    $response->assertSuccessful();
    expect(Content::withTrashed()->find($content->id)->trashed())->toBeTrue();
});

// === Stage Progression ===

it('can move content to next stage', function () {
    $content = Content::factory()->create([
        'stage' => 'idea',
        'created_by' => $this->employee->id,
    ]);

    // Create stage records like the store method does
    foreach (['idea', 'shooting', 'editing', 'posting'] as $stage) {
        ContentStage::create([
            'content_id' => $content->id,
            'stage' => $stage,
            'status' => $stage === 'idea' ? 'in_progress' : 'pending',
            'started_at' => $stage === 'idea' ? now() : null,
        ]);
    }

    $response = $this->patchJson("/api/cms/contents/{$content->id}/stage", [
        'stage' => 'shooting',
    ]);

    $response->assertSuccessful();
    expect($content->fresh()->stage)->toBe('shooting');

    $ideaStage = ContentStage::where('content_id', $content->id)->where('stage', 'idea')->first();
    expect($ideaStage->status)->toBe('completed');

    $shootingStage = ContentStage::where('content_id', $content->id)->where('stage', 'shooting')->first();
    expect($shootingStage->status)->toBe('in_progress');
});

// === Content Stats ===

it('can add stats to content', function () {
    $content = Content::factory()->posted()->create(['created_by' => $this->employee->id]);

    $response = $this->postJson("/api/cms/contents/{$content->id}/stats", [
        'views' => 500,
        'likes' => 50,
        'comments' => 10,
        'shares' => 5,
    ]);

    $response->assertCreated();
    expect(ContentStat::where('content_id', $content->id)->count())->toBe(1);
});

it('auto-flags content when stats exceed threshold', function () {
    $content = Content::factory()->posted()->create(['created_by' => $this->employee->id]);

    $this->postJson("/api/cms/contents/{$content->id}/stats", [
        'views' => 15000,
        'likes' => 100,
        'comments' => 10,
        'shares' => 5,
    ]);

    expect($content->fresh()->is_flagged_for_ads)->toBeTrue();
});

// === Mark for Ads ===

it('can toggle mark for ads', function () {
    $content = Content::factory()->create(['created_by' => $this->employee->id]);

    // Mark for ads
    $response = $this->patchJson("/api/cms/contents/{$content->id}/mark-for-ads");
    $response->assertSuccessful();
    expect($content->fresh()->is_marked_for_ads)->toBeTrue();

    // Unmark for ads
    $response = $this->patchJson("/api/cms/contents/{$content->id}/mark-for-ads");
    $response->assertSuccessful();
    expect($content->fresh()->is_marked_for_ads)->toBeFalse();
});

// === Kanban View ===

it('can get kanban view', function () {
    foreach (['idea', 'shooting', 'editing', 'posting', 'posted'] as $stage) {
        Content::factory()->create([
            'stage' => $stage,
            'created_by' => $this->employee->id,
        ]);
    }

    $response = $this->getJson('/api/cms/contents/kanban');

    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data)->toHaveKeys(['idea', 'shooting', 'editing', 'posting', 'posted']);
});

// === Calendar View ===

it('can get calendar view', function () {
    Content::factory()->create([
        'due_date' => now()->startOfMonth()->addDays(5),
        'created_by' => $this->employee->id,
    ]);

    $response = $this->getJson('/api/cms/contents/calendar?'.http_build_query([
        'month' => now()->month,
        'year' => now()->year,
    ]));

    $response->assertSuccessful();
    expect($response->json('data'))->not->toBeEmpty();
});
