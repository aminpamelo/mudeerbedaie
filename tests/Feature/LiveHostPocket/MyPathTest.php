<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeChecklistItem;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\MenteeStageTransition;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders My Path with the enrollment for an enrolled host', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'current_stage_id' => $program->stages()->orderBy('position')->first()->id,
        'status' => 'active',
    ]);
    app(MenteeStageTransition::class)->enterFirstStage($mentee);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('enrollment')
            ->where('enrollment.program.title', $program->title)
            ->has('enrollment.stages', 5)
            ->has('enrollment.checklist')
        );
});

it('splits the host checklist into program and individual tasks with overdue flags', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'current_stage_id' => $program->stages()->orderBy('position')->first()->id,
        'status' => 'active',
    ]);
    app(MenteeStageTransition::class)->enterFirstStage($mentee);

    LiveHostMenteeChecklistItem::factory()->create([
        'mentee_id' => $mentee->id, 'source' => 'template', 'title' => 'Program step', 'status' => 'pending',
    ]);
    LiveHostMenteeChecklistItem::factory()->create([
        'mentee_id' => $mentee->id, 'source' => 'custom', 'title' => 'Personal coaching', 'status' => 'pending',
        'due_at' => now()->subDay(),
    ]);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('enrollment.checklist.program', 1)
            ->has('enrollment.checklist.individual', 1)
            ->where('enrollment.checklist.individual.0.title', 'Personal coaching')
            ->where('enrollment.checklist.individual.0.is_overdue', true)
            ->where('enrollment.checklist.individual_total', 1)
        );
});

it('renders an empty state for a host not in any program', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('enrollment', null));
});

it('only counts the active enrollment, ignoring a dropped one', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    LiveHostMentee::factory()->create([
        'mentee_user_id' => $host->id,
        'status' => 'dropped',
    ]);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('enrollment', null));
});

it('blocks non-live-host roles from My Path', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin_livehost']))
        ->get('/live-host/my-path')
        ->assertForbidden();
});
