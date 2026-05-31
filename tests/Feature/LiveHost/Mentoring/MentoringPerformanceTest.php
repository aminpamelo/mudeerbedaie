<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeMonthlyScore;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function perfPic(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

function perfMentee(): LiveHostMentee
{
    $program = LiveHostMentoringProgram::factory()->active()->create();

    return LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'status' => 'active',
    ]);
}

it('records a monthly score for a mentee', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", [
            'year' => 2026, 'month' => 5, 'score' => 82, 'notes' => 'Strong month',
        ])
        ->assertNoContent();

    $row = LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->year)->toBe(2026)
        ->and($row->month)->toBe(5)
        ->and($row->score)->toBe(82)
        ->and($row->notes)->toBe('Strong month');
});

it('upserts the same month instead of duplicating', function () {
    $mentee = perfMentee();
    $pic = perfPic();

    $this->actingAs($pic)->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'score' => 60]);
    $this->actingAs($pic)->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'score' => 90]);

    expect(LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->where('year', 2026)->where('month', 5)->count())->toBe(1)
        ->and(LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->first()->score)->toBe(90);
});

it('allows clearing a score by sending null', function () {
    $mentee = perfMentee();
    $pic = perfPic();

    $this->actingAs($pic)->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'score' => 70]);
    $this->actingAs($pic)->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'score' => null]);

    expect(LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->first()->score)->toBeNull();
});

it('rejects an out-of-range score', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patchJson("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'score' => 150])
        ->assertStatus(422);

    $this->actingAs(perfPic())
        ->patchJson("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 13, 'score' => 50])
        ->assertStatus(422);
});

it('exposes 6 months of performance data on the program editor', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'status' => 'active',
    ]);
    LiveHostMenteeMonthlyScore::create(['mentee_id' => $mentee->id, 'year' => 2026, 'month' => 5, 'score' => 77]);

    $this->actingAs(perfPic())
        ->get("/livehost/mentoring/programs/{$program->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('performance.months', 6)
            ->has('performance.mentees', 1)
            ->where('performance.mentees.0.id', $mentee->id)
        );
});

it('blocks non-PIC roles from recording scores', function () {
    $mentee = perfMentee();

    $this->actingAs(User::factory()->create(['role' => 'live_host']))
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'score' => 50])
        ->assertForbidden();
});
