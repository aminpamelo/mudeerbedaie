<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeChecklistItem;
use App\Models\LiveHostMentoringActivity;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\MenteeStageTransition;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function coachHost(): User
{
    return User::factory()->create(['role' => 'live_host']);
}

/**
 * A program led by $leader with one active mentee (no per-mentee override, so
 * the leader is the effective mentor). Returns [program, mentee].
 *
 * @return array{0: LiveHostMentoringProgram, 1: LiveHostMentee}
 */
function coachProgramWithMentee(User $leader): array
{
    $program = LiveHostMentoringProgram::factory()->active()->create(['leader_user_id' => $leader->id]);
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => coachHost()->id,
        'current_stage_id' => $program->stages()->orderBy('position')->first()->id,
        'status' => 'active',
    ]);
    app(MenteeStageTransition::class)->enterFirstStage($mentee);

    return [$program, $mentee];
}

it('lists only the mentees a top host is responsible for', function () {
    $leader = coachHost();
    [, $mine] = coachProgramWithMentee($leader);
    // A mentee in another leader's program — must not appear.
    coachProgramWithMentee(coachHost());

    $this->actingAs($leader)
        ->get('/live-host/mentees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('mentees', 1)->where('mentees.0.id', $mine->id));
});

it('includes a per-mentee override even across programs', function () {
    $leader = coachHost();
    $otherProgram = LiveHostMentoringProgram::factory()->active()->create(['leader_user_id' => coachHost()->id]);
    $override = LiveHostMentee::factory()->create([
        'program_id' => $otherProgram->id,
        'mentee_user_id' => coachHost()->id,
        'mentor_user_id' => $leader->id,
        'status' => 'active',
    ]);

    $this->actingAs($leader)
        ->get('/live-host/mentees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('mentees', 1)->where('mentees.0.id', $override->id));
});

it('lets the mentor open a mentee they own but forbids others', function () {
    $leader = coachHost();
    [, $mine] = coachProgramWithMentee($leader);
    [, $notMine] = coachProgramWithMentee(coachHost());

    $this->actingAs($leader)->get("/live-host/mentees/{$mine->id}")->assertOk();
    $this->actingAs($leader)->get("/live-host/mentees/{$notMine->id}")->assertForbidden();
});

it('lets the mentor log a coaching activity against their mentee', function () {
    $leader = coachHost();
    [$program, $mentee] = coachProgramWithMentee($leader);

    $this->actingAs($leader)
        ->post("/live-host/mentees/{$mentee->id}/activities", ['type' => 'coaching', 'title' => 'Reviewed last live'])
        ->assertRedirect();

    $activity = LiveHostMentoringActivity::where('mentee_id', $mentee->id)->first();
    expect($activity)->not->toBeNull()
        ->and($activity->leader_user_id)->toBe($leader->id)
        ->and($activity->program_id)->toBe($program->id);
});

it('forbids logging an activity against a mentee the host does not mentor', function () {
    $leader = coachHost();
    [, $notMine] = coachProgramWithMentee(coachHost());

    $this->actingAs($leader)
        ->post("/live-host/mentees/{$notMine->id}/activities", ['type' => 'coaching', 'title' => 'Nope'])
        ->assertForbidden();
});

it('lets the mentor toggle a checklist item', function () {
    $leader = coachHost();
    [, $mentee] = coachProgramWithMentee($leader);
    $item = LiveHostMenteeChecklistItem::factory()->create(['mentee_id' => $mentee->id]);

    $this->actingAs($leader)
        ->patch("/live-host/mentees/{$mentee->id}/checklist/{$item->id}/toggle")
        ->assertRedirect();

    expect($item->fresh()->status)->toBe('done');
});

it('lets the mentor assign a level', function () {
    $leader = coachHost();
    [, $mentee] = coachProgramWithMentee($leader);
    $level = LiveHostMentoringLevel::where('name', 'Pro')->first();

    $this->actingAs($leader)
        ->patch("/live-host/mentees/{$mentee->id}/level", ['level_id' => $level->id, 'source' => 'manual'])
        ->assertRedirect();

    expect($mentee->fresh()->level_id)->toBe($level->id);
});

it('lets the mentor move stages and graduate, flagging top-host eligibility', function () {
    $leader = coachHost();
    [$program, $mentee] = coachProgramWithMentee($leader);
    $finalStage = $program->stages()->where('is_final', true)->first();

    $this->actingAs($leader)
        ->patch("/live-host/mentees/{$mentee->id}/stage", ['to_stage_id' => $finalStage->id])
        ->assertRedirect();
    expect($mentee->fresh()->current_stage_id)->toBe($finalStage->id);

    $this->actingAs($leader)
        ->post("/live-host/mentees/{$mentee->id}/graduate")
        ->assertRedirect();

    $mentee->refresh();
    expect($mentee->status)->toBe('graduated')
        ->and($mentee->menteeUser->fresh()->is_top_host_eligible)->toBeTrue();
});

it('forbids a non-live-host from the mentor cockpit', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin_livehost']))
        ->get('/live-host/mentees')
        ->assertForbidden();
});
