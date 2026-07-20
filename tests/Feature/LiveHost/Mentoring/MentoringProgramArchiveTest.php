<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function archivePic(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

/*
|--------------------------------------------------------------------------
| Desk: archive / restore / list filtering
|--------------------------------------------------------------------------
*/

it('archives a program (reversibly, no data loss)', function () {
    $program = LiveHostMentoringProgram::factory()->create();
    LiveHostMentee::factory()->create(['program_id' => $program->id]);

    $this->actingAs(archivePic())
        ->patch("/livehost/mentoring/programs/{$program->id}/archive")
        ->assertRedirect();

    expect($program->fresh()->archived_at)->not->toBeNull()
        // Archiving never touches the mentees.
        ->and($program->mentees()->count())->toBe(1);
});

it('restores an archived program back to active', function () {
    $program = LiveHostMentoringProgram::factory()->create(['archived_at' => now()]);

    $this->actingAs(archivePic())
        ->patch("/livehost/mentoring/programs/{$program->id}/restore")
        ->assertRedirect();

    expect($program->fresh()->archived_at)->toBeNull();
});

it('excludes archived programs from the default list and shows them under view=archived', function () {
    LiveHostMentoringProgram::factory()->create(['title' => 'Active One']);
    LiveHostMentoringProgram::factory()->create(['title' => 'Archived One', 'archived_at' => now()]);

    $this->actingAs(archivePic())->get('/livehost/mentoring/programs')
        ->assertInertia(fn (Assert $p) => $p
            ->where('view', 'active')
            ->where('archivedCount', 1)
            ->has('programs.data', 1)
            ->where('programs.data.0.title', 'Active One'));

    $this->actingAs(archivePic())->get('/livehost/mentoring/programs?view=archived')
        ->assertInertia(fn (Assert $p) => $p
            ->where('view', 'archived')
            ->has('programs.data', 1)
            ->where('programs.data.0.title', 'Archived One')
            ->where('programs.data.0.archived', true));
});

it('excludes archived programs from the Mentoring Overview grid', function () {
    LiveHostMentoringProgram::factory()->active()->create(['title' => 'Live Cohort']);
    LiveHostMentoringProgram::factory()->active()->create(['title' => 'Old Cohort', 'archived_at' => now()]);

    $this->actingAs(archivePic())->get('/livehost/mentoring/overview')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->has('programs', 1)
            ->where('programs.0.program.title', 'Live Cohort'));
});

/*
|--------------------------------------------------------------------------
| Pocket: an archived program's performance is hidden from the host
|--------------------------------------------------------------------------
*/

it('hides an archived program\'s path + performance from the host\'s Pocket My Path', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $program = LiveHostMentoringProgram::factory()->create(['title' => 'Cohort A']);
    LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'status' => 'active',
    ]);

    // Visible while the program is live.
    $this->actingAs($host)->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('MyPath', false)->where('enrollment.program.title', 'Cohort A'));

    // Archiving hides the whole path (and its performance).
    $program->update(['archived_at' => now()]);
    $this->actingAs($host)->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('MyPath', false)->where('enrollment', null));
});

it('shows the host\'s My Path only while actively enrolled (not after graduating)', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $program = LiveHostMentoringProgram::factory()->create(['title' => 'Cohort B']);
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'status' => 'active',
    ]);

    $this->actingAs($host)->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('MyPath', false)->where('enrollment.program.title', 'Cohort B'));

    // Graduating (program still live) removes the path from Pocket.
    $mentee->update(['status' => 'graduated']);
    $this->actingAs($host)->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('MyPath', false)->where('enrollment', null));
});

it('hides an archived program\'s performance summary from the host\'s Pocket home', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $program = LiveHostMentoringProgram::factory()->create(['archived_at' => now()]);
    LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'status' => 'active',
    ]);

    $this->actingAs($host)->get('/live-host')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('performanceSummary', null));
});
