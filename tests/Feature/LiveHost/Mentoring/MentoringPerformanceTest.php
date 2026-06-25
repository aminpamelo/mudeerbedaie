<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeMonthlyScore;
use App\Models\LiveHostMentoringLevel;
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

it('records monthly KPI metrics for a mentee', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", [
            'year' => 2026, 'month' => 5, 'attitude_score' => 82, 'sales_quantity' => 140, 'notes' => 'Strong month',
        ])
        ->assertRedirect();

    $row = LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->year)->toBe(2026)
        ->and($row->month)->toBe(5)
        ->and($row->attitude_score)->toBe(82)
        ->and((float) $row->sales_quantity)->toBe(140.0)
        ->and($row->notes)->toBe('Strong month');
});

it('stores the sales RM value with sen (2 decimals)', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", [
            'year' => 2026, 'month' => 6, 'sales_quantity' => 2909.50,
        ])
        ->assertRedirect();

    $row = LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->first();
    expect((float) $row->sales_quantity)->toBe(2909.50);
});

it('redirects back instead of returning 204 so Inertia does not show a blank modal', function () {
    $mentee = perfMentee();
    $editUrl = "/livehost/mentoring/programs/{$mentee->program_id}/edit";

    $response = $this->actingAs(perfPic())
        ->from($editUrl)
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", [
            'year' => 2026, 'month' => 6, 'attitude_score' => 10,
        ]);

    $response->assertStatus(302);
    $response->assertRedirect($editUrl);
});

it('upserts the same month instead of duplicating', function () {
    $mentee = perfMentee();
    $pic = perfPic();

    $this->actingAs($pic)->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'attitude_score' => 60]);
    $this->actingAs($pic)->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'attitude_score' => 90]);

    expect(LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->where('year', 2026)->where('month', 5)->count())->toBe(1)
        ->and(LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->first()->attitude_score)->toBe(90);
});

it('allows clearing a score by sending null', function () {
    $mentee = perfMentee();
    $pic = perfPic();

    $this->actingAs($pic)->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'attitude_score' => 70, 'sales_quantity' => 50]);
    $this->actingAs($pic)->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'attitude_score' => null, 'sales_quantity' => null]);

    $row = LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->first();
    expect($row->attitude_score)->toBeNull()
        ->and($row->sales_quantity)->toBeNull();
});

it('rejects out-of-range KPI values', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patchJson("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'attitude_score' => 150])
        ->assertStatus(422);

    $this->actingAs(perfPic())
        ->patchJson("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'sales_quantity' => -5])
        ->assertStatus(422);

    $this->actingAs(perfPic())
        ->patchJson("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 13, 'attitude_score' => 50])
        ->assertStatus(422);
});

it('exposes a full calendar year of performance months, January first', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'status' => 'active',
    ]);
    LiveHostMenteeMonthlyScore::create(['mentee_id' => $mentee->id, 'year' => 2026, 'month' => 5, 'attitude_score' => 77, 'sales_quantity' => 90]);

    $year = now()->year;

    $this->actingAs(perfPic())
        ->get("/livehost/mentoring/programs/{$program->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('performance.months', 12)
            ->where('performance.months.0.value', "{$year}-01")
            ->where('performance.months.0.month', 1)
            ->where('performance.months.11.month', 12)
            ->has('performance.mentees', 1)
            ->where('performance.mentees.0.id', $mentee->id)
            ->where('performance.mentees.0.scores.2026-05.attitude', 77)
            ->where('performance.mentees.0.scores.2026-05.sales', 90)
        );
});

it('exposes the level sales target with each mentee for the Sales KPI', function () {
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 120]);
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'status' => 'active',
        'level_id' => $level->id,
    ]);

    $this->actingAs(perfPic())
        ->get("/livehost/mentoring/programs/{$program->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('performance.mentees.0.id', $mentee->id)
            ->where('performance.mentees.0.sales_target', 120)
        );
});

it('blocks non-PIC roles from recording scores', function () {
    $mentee = perfMentee();

    $this->actingAs(User::factory()->create(['role' => 'live_host']))
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'score' => 50])
        ->assertForbidden();
});
