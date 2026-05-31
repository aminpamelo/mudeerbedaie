<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
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
