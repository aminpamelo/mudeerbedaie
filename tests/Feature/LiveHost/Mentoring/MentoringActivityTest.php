<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringActivity;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\MentorActivityIndicator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function activityPic(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

it('logs a coaching activity against the program leader', function () {
    $leader = User::factory()->create(['role' => 'live_host']);
    $program = LiveHostMentoringProgram::factory()->active()->create(['leader_user_id' => $leader->id]);
    $pic = activityPic();

    $this->actingAs($pic)
        ->post("/livehost/mentoring/programs/{$program->id}/activities", [
            'type' => 'coaching',
            'title' => '1:1 on hook openings',
            'occurred_at' => now()->toIso8601String(),
        ])
        ->assertRedirect();

    $activity = LiveHostMentoringActivity::where('program_id', $program->id)->first();
    expect($activity)->not->toBeNull()
        ->and($activity->leader_user_id)->toBe($leader->id)
        ->and($activity->created_by)->toBe($pic->id)
        ->and($activity->type)->toBe('coaching')
        ->and($activity->mentee_id)->toBeNull();
});

it('rejects logging an activity for a mentee in another program', function () {
    $programA = LiveHostMentoringProgram::factory()->active()->create();
    $programB = LiveHostMentoringProgram::factory()->active()->create();
    $foreignMentee = LiveHostMentee::factory()->create([
        'program_id' => $programB->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
    ]);

    $this->actingAs(activityPic())
        ->post("/livehost/mentoring/programs/{$programA->id}/activities", [
            'type' => 'meeting',
            'title' => 'Wrong program',
            'occurred_at' => now()->toIso8601String(),
            'mentee_id' => $foreignMentee->id,
        ])
        ->assertStatus(422);
});

it('deletes a logged activity', function () {
    $activity = LiveHostMentoringActivity::factory()->create();

    $this->actingAs(activityPic())
        ->delete("/livehost/mentoring/activities/{$activity->id}")
        ->assertRedirect();

    expect(LiveHostMentoringActivity::find($activity->id))->toBeNull();
});

it('reports green for a leader active within 7 days', function () {
    $leader = User::factory()->create(['role' => 'live_host']);
    LiveHostMentoringActivity::factory()->create([
        'leader_user_id' => $leader->id,
        'occurred_at' => now()->subDays(3),
    ]);

    $indicator = app(MentorActivityIndicator::class)->forLeader($leader->id);
    expect($indicator['level'])->toBe('green')
        ->and($indicator['count30'])->toBe(1);
});

it('reports amber for a leader whose last activity is 8-14 days old', function () {
    $leader = User::factory()->create(['role' => 'live_host']);
    LiveHostMentoringActivity::factory()->create([
        'leader_user_id' => $leader->id,
        'occurred_at' => now()->subDays(10),
    ]);

    expect(app(MentorActivityIndicator::class)->forLeader($leader->id)['level'])->toBe('amber');
});

it('reports red for a leader with no recent activity', function () {
    $leader = User::factory()->create(['role' => 'live_host']);
    LiveHostMentoringActivity::factory()->create([
        'leader_user_id' => $leader->id,
        'occurred_at' => now()->subDays(40),
    ]);

    $indicator = app(MentorActivityIndicator::class)->forLeader($leader->id);
    expect($indicator['level'])->toBe('red')
        ->and($indicator['count30'])->toBe(0);
});

it('reports none when there is no leader', function () {
    expect(app(MentorActivityIndicator::class)->forLeader(null)['level'])->toBe('none');
});
