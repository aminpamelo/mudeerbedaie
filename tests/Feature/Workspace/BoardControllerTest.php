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

test('board page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get('/workspace/board')
        ->assertSuccessful();
});

test('board page returns columns and grouped tasks', function () {
    $project = TmsProject::factory()->create(['owner_id' => $this->user->id]);

    Task::factory()->create(['status' => 'pending', 'project_id' => $project->id]);
    Task::factory()->create(['status' => 'in_progress', 'project_id' => $project->id]);
    Task::factory()->create(['status' => 'completed', 'project_id' => $project->id]);

    $response = $this->actingAs($this->user)
        ->get('/workspace/board')
        ->assertSuccessful();

    $page = $response->viewData('page');

    expect($page['props']['columns'])->toHaveCount(4)
        ->and($page['props']['tasks'])->toHaveKeys(['pending', 'in_progress', 'review', 'completed']);
});

test('board filters tasks by project', function () {
    $projectA = TmsProject::factory()->create(['owner_id' => $this->user->id]);
    $projectB = TmsProject::factory()->create(['owner_id' => $this->user->id]);

    Task::factory()->create(['status' => 'pending', 'project_id' => $projectA->id]);
    Task::factory()->create(['status' => 'pending', 'project_id' => $projectB->id]);

    $response = $this->actingAs($this->user)
        ->get('/workspace/board?project_id='.$projectA->id)
        ->assertSuccessful();

    $page = $response->viewData('page');
    $pendingTasks = $page['props']['tasks']['pending'];

    expect($pendingTasks)->toHaveCount(1);
});

test('board is not accessible to unauthenticated users', function () {
    $this->get('/workspace/board')
        ->assertRedirect();
});
