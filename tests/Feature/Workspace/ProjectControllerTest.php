<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\TmsProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
});

test('projects index loads for authenticated user', function () {
    TmsProject::factory()->count(3)->create(['owner_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->get('/workspace/projects')
        ->assertSuccessful();

    $page = $response->viewData('page');

    expect($page['props']['projects'])->toHaveCount(3);
});

test('project can be created', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/workspace/projects', [
            'name' => 'Test Project',
            'description' => 'A test project',
            'color' => '#ff0000',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('tms_projects', [
        'name' => 'Test Project',
        'owner_id' => $this->user->id,
    ]);

    // Owner should be added as project member
    $project = TmsProject::where('name', 'Test Project')->first();
    expect($project->members()->where('user_id', $this->user->id)->exists())->toBeTrue();
});

test('project creation requires a name', function () {
    $this->actingAs($this->user)
        ->postJson('/workspace/projects', [
            'description' => 'Missing name',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('project detail page loads', function () {
    $project = TmsProject::factory()->create(['owner_id' => $this->user->id]);
    Task::factory()->count(2)->create(['project_id' => $project->id]);

    $response = $this->actingAs($this->user)
        ->get("/workspace/projects/{$project->id}")
        ->assertSuccessful();

    $page = $response->viewData('page');

    expect($page['props']['project']['id'])->toBe($project->id)
        ->and($page['props']['tasks'])->toHaveCount(2);
});

test('project can be updated', function () {
    $project = TmsProject::factory()->create(['owner_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->patchJson("/workspace/projects/{$project->id}", [
            'name' => 'Updated Name',
            'status' => 'on_hold',
        ])
        ->assertSuccessful();

    $project->refresh();

    expect($project->name)->toBe('Updated Name')
        ->and($project->status)->toBe('on_hold');
});

test('project can be deleted', function () {
    $project = TmsProject::factory()->create(['owner_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->deleteJson("/workspace/projects/{$project->id}")
        ->assertSuccessful();

    $this->assertSoftDeleted('tms_projects', ['id' => $project->id]);
});

test('member can be added to a project', function () {
    $project = TmsProject::factory()->create(['owner_id' => $this->user->id]);
    $member = User::factory()->create(['role' => 'employee']);

    $this->actingAs($this->user)
        ->postJson("/workspace/projects/{$project->id}/members", [
            'user_id' => $member->id,
            'role' => 'member',
        ])
        ->assertSuccessful();

    expect($project->members()->where('user_id', $member->id)->exists())->toBeTrue();
});

test('member can be removed from a project', function () {
    $project = TmsProject::factory()->create(['owner_id' => $this->user->id]);
    $member = User::factory()->create(['role' => 'employee']);
    $project->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($this->user)
        ->deleteJson("/workspace/projects/{$project->id}/members/{$member->id}")
        ->assertSuccessful();

    expect($project->members()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('projects are not accessible to unauthenticated users', function () {
    $this->get('/workspace/projects')
        ->assertRedirect();
});
