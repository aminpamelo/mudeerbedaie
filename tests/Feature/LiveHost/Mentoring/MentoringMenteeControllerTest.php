<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeMonthlyScore;
use App\Models\LiveHostMenteeStage;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\MenteeStageTransition;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function pic(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

function liveHost(array $attrs = []): User
{
    return User::factory()->create(array_merge(['role' => 'live_host'], $attrs));
}

function programWithLeader(): LiveHostMentoringProgram
{
    return LiveHostMentoringProgram::factory()->active()->create([
        'leader_user_id' => liveHost()->id,
    ]);
}

it('lets a PIC enrol a live host as a mentee and opens the first stage', function () {
    $program = programWithLeader();
    $host = liveHost();

    $this->actingAs(pic())
        ->post("/livehost/mentoring/programs/{$program->id}/mentees", [
            'mentee_user_id' => $host->id,
        ])
        ->assertRedirect();

    $mentee = LiveHostMentee::where('mentee_user_id', $host->id)->first();
    expect($mentee)->not->toBeNull()
        ->and($mentee->status)->toBe('active')
        ->and($mentee->current_stage_id)->toBe($program->stages()->orderBy('position')->first()->id)
        ->and(str_starts_with($mentee->mentee_number, 'LHM-'))->toBeTrue();

    expect(LiveHostMenteeStage::where('mentee_id', $mentee->id)->whereNull('exited_at')->count())->toBe(1);
    expect($mentee->history()->where('action', 'enrolled')->exists())->toBeTrue();
});

it('rejects enrolling a host who is already an active mentee', function () {
    $program = programWithLeader();
    $host = liveHost();
    LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'status' => 'active',
    ]);

    $this->actingAs(pic())
        ->post("/livehost/mentoring/programs/{$program->id}/mentees", [
            'mentee_user_id' => $host->id,
        ])
        ->assertStatus(422);
});

it('lets a PIC enrol a live host assistant (part-time host) as a mentee', function () {
    $program = programWithLeader();
    $assistant = User::factory()->create(['role' => 'livehost_assistant']);

    $this->actingAs(pic())
        ->post("/livehost/mentoring/programs/{$program->id}/mentees", [
            'mentee_user_id' => $assistant->id,
        ])
        ->assertRedirect();

    expect(LiveHostMentee::where('mentee_user_id', $assistant->id)->where('status', 'active')->exists())->toBeTrue();
});

it('rejects enrolling a non-live-host user', function () {
    $program = programWithLeader();
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs(pic())
        ->from("/livehost/mentoring/mentees?program={$program->id}")
        ->post("/livehost/mentoring/programs/{$program->id}/mentees", [
            'mentee_user_id' => $student->id,
        ])
        ->assertSessionHasErrors('mentee_user_id');
});

it('advances a mentee to the next stage', function () {
    $program = programWithLeader();
    $stages = $program->stages()->orderBy('position')->get();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => liveHost()->id,
        'current_stage_id' => $stages[0]->id,
        'status' => 'active',
    ]);
    app(\App\Services\Mentoring\MenteeStageTransition::class)->enterFirstStage($mentee);

    $this->actingAs(pic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/stage", ['to_stage_id' => $stages[1]->id])
        ->assertRedirect();

    expect($mentee->fresh()->current_stage_id)->toBe($stages[1]->id)
        ->and($mentee->history()->where('action', 'advanced')->exists())->toBeTrue();
});

it('graduates a mentee on the final stage and marks the host top-host eligible', function () {
    $program = programWithLeader();
    $finalStage = $program->stages()->where('is_final', true)->first();
    $host = liveHost(['is_top_host_eligible' => false]);
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'current_stage_id' => $finalStage->id,
        'status' => 'active',
    ]);
    app(\App\Services\Mentoring\MenteeStageTransition::class)->enterFirstStage($mentee);

    $this->actingAs(pic())
        ->post("/livehost/mentoring/mentees/{$mentee->id}/graduate")
        ->assertRedirect();

    $mentee->refresh();
    expect($mentee->status)->toBe('graduated')
        ->and($mentee->graduated_at)->not->toBeNull()
        ->and($host->fresh()->is_top_host_eligible)->toBeTrue();
    expect(LiveHostMenteeStage::where('mentee_id', $mentee->id)->whereNull('exited_at')->count())->toBe(0);
});

it('refuses to graduate a mentee who is not on the final stage', function () {
    $program = programWithLeader();
    $firstStage = $program->stages()->orderBy('position')->first();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => liveHost()->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'active',
    ]);
    app(\App\Services\Mentoring\MenteeStageTransition::class)->enterFirstStage($mentee);

    $this->actingAs(pic())
        ->post("/livehost/mentoring/mentees/{$mentee->id}/graduate")
        ->assertStatus(422);

    expect($mentee->fresh()->status)->toBe('active');
});

it('drops then restores a mentee', function () {
    $program = programWithLeader();
    $firstStage = $program->stages()->orderBy('position')->first();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => liveHost()->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'active',
    ]);
    app(\App\Services\Mentoring\MenteeStageTransition::class)->enterFirstStage($mentee);

    $this->actingAs(pic())->patch("/livehost/mentoring/mentees/{$mentee->id}/drop")->assertRedirect();
    expect($mentee->fresh()->status)->toBe('dropped');

    $this->actingAs(pic())->patch("/livehost/mentoring/mentees/{$mentee->id}/restore")->assertRedirect();
    expect($mentee->fresh()->status)->toBe('active');
    expect(LiveHostMenteeStage::where('mentee_id', $mentee->id)->whereNull('exited_at')->count())->toBe(1);
});

it('updates the mentor override and stage row assignee together', function () {
    $program = programWithLeader();
    $firstStage = $program->stages()->orderBy('position')->first();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => liveHost()->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'active',
    ]);
    app(\App\Services\Mentoring\MenteeStageTransition::class)->enterFirstStage($mentee);
    $newMentor = liveHost();

    $this->actingAs(pic())
        ->from("/livehost/mentoring/programs/{$program->id}/edit")
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/current-stage", [
            'mentor_user_id' => $newMentor->id,
            'stage_notes' => 'Pair with senior host',
        ])
        ->assertRedirect();

    expect($mentee->fresh()->mentor_user_id)->toBe($newMentor->id);
    $openRow = LiveHostMenteeStage::where('mentee_id', $mentee->id)->whereNull('exited_at')->first();
    expect($openRow->assignee_id)->toBe($newMentor->id)
        ->and($openRow->stage_notes)->toBe('Pair with senior host');
});

it('blocks non-PIC roles from the mentee board', function () {
    $program = programWithLeader();

    $this->actingAs(liveHost())->get('/livehost/mentoring/mentees')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'livehost_assistant']))
        ->get('/livehost/mentoring/mentees')->assertForbidden();
});

it('allows a PIC to load the mentee board', function () {
    programWithLeader();

    $this->actingAs(pic())->get('/livehost/mentoring/mentees')->assertOk();
});

it('loads a mentee detail page with KPI and level data', function () {
    $program = programWithLeader();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => liveHost()->id,
        'current_stage_id' => $program->stages()->orderBy('position')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs(pic())->get("/livehost/mentoring/mentees/{$mentee->id}")->assertOk();
});

it('lets a PIC permanently remove a mentee but keeps the host account', function () {
    $program = programWithLeader();
    $host = liveHost();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'current_stage_id' => $program->stages()->orderBy('position')->first()->id,
        'status' => 'active',
    ]);
    app(MenteeStageTransition::class)->enterFirstStage($mentee);
    LiveHostMenteeMonthlyScore::create(['mentee_id' => $mentee->id, 'year' => 2026, 'month' => 5, 'attitude_score' => 80, 'sales_quantity' => 100]);
    $mentee->checklistItems()->create(['title' => 'Task', 'is_required' => true, 'position' => 0, 'status' => 'pending']);
    $mentee->history()->create(['from_stage_id' => null, 'to_stage_id' => $mentee->current_stage_id, 'action' => 'enrolled']);

    $this->actingAs(pic())
        ->delete("/livehost/mentoring/mentees/{$mentee->id}")
        ->assertRedirect();

    expect(LiveHostMentee::find($mentee->id))->toBeNull()
        ->and(LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->count())->toBe(0)
        ->and(LiveHostMenteeStage::where('mentee_id', $mentee->id)->count())->toBe(0)
        ->and(User::find($host->id))->not->toBeNull();
});

it('blocks non-PIC roles from deleting a mentee', function () {
    $program = programWithLeader();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => liveHost()->id,
        'status' => 'active',
    ]);

    $this->actingAs(liveHost())
        ->delete("/livehost/mentoring/mentees/{$mentee->id}")
        ->assertForbidden();

    expect(LiveHostMentee::find($mentee->id))->not->toBeNull();
});
