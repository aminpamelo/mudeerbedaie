<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringActivity;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveHostMentoringStage;
use App\Models\User;

/**
 * The mentoring module grants livehost_assistant full view + manage parity
 * (commit 9f8750fb opened the route middleware to the assistant). The
 * FormRequest authorize() guards were left PIC-only, so every write action
 * 403'd with "This action is unauthorized." These tests lock the parity so the
 * route middleware and the FormRequest guards cannot drift apart again.
 */
function mentoringAssistant(): User
{
    return User::factory()->liveHostAssistant()->create();
}

it('lets an assistant log a mentoring activity', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();

    $this->actingAs(mentoringAssistant())
        ->post("/livehost/mentoring/programs/{$program->id}/activities", [
            'type' => 'coaching',
            'title' => 'Weekly coaching call',
            'notes' => 'Reviewed KPIs together.',
            'occurred_at' => now()->toIso8601String(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(LiveHostMentoringActivity::query()
        ->where('program_id', $program->id)
        ->where('title', 'Weekly coaching call')
        ->exists())->toBeTrue();
});

it('lets an assistant create a mentoring program', function () {
    $this->actingAs(mentoringAssistant())
        ->post('/livehost/mentoring/programs', ['title' => 'Assistant Cohort'])
        ->assertRedirect();

    expect(LiveHostMentoringProgram::query()->where('title', 'Assistant Cohort')->exists())->toBeTrue();
});

it('lets an assistant enroll a mentee', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    LiveHostMentoringStage::factory()->create([
        'program_id' => $program->id,
        'position' => 1,
        'is_final' => true,
    ]);
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs(mentoringAssistant())
        ->post("/livehost/mentoring/programs/{$program->id}/mentees", [
            'mentee_user_id' => $host->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(LiveHostMentee::query()
        ->where('program_id', $program->id)
        ->where('mentee_user_id', $host->id)
        ->exists())->toBeTrue();
});

it('lets an assistant assign a mentee level', function () {
    $mentee = LiveHostMentee::factory()->create();
    $level = LiveHostMentoringLevel::factory()->create();

    $this->actingAs(mentoringAssistant())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/level", ['level_id' => $level->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($mentee->fresh()->level_id)->toEqual($level->id);
});

it('lets an assistant update a mentee current stage', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $stage = LiveHostMentoringStage::factory()->create([
        'program_id' => $program->id,
        'position' => 1,
    ]);
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'current_stage_id' => $stage->id,
    ]);

    $response = $this->actingAs(mentoringAssistant())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/current-stage", [
            'stage_notes' => 'Doing well, ready to advance soon.',
        ]);

    expect($response->status())->not->toBe(403);
    $response->assertSessionHasNoErrors();
});

it('still blocks a live_host from logging a mentoring activity', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();

    $this->actingAs(User::factory()->create(['role' => 'live_host']))
        ->post("/livehost/mentoring/programs/{$program->id}/activities", [
            'type' => 'coaching',
            'title' => 'Should be blocked',
            'occurred_at' => now()->toIso8601String(),
        ])
        ->assertForbidden();
});
