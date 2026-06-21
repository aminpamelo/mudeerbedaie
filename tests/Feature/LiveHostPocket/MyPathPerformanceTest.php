<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeMonthlyScore;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Enrol a host into an active program at its first stage, with the given level.
 */
function enrolHost(User $host, ?LiveHostMentoringLevel $level = null, string $status = 'active'): LiveHostMentee
{
    $program = LiveHostMentoringProgram::factory()->active()->create();

    return LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'current_stage_id' => $program->stages()->orderBy('position')->first()->id,
        'level_id' => $level?->id,
        'status' => $status,
    ]);
}

function recordScore(LiveHostMentee $mentee, int $year, int $month, ?int $attitude, ?int $sales): void
{
    LiveHostMenteeMonthlyScore::create([
        'mentee_id' => $mentee->id,
        'year' => $year,
        'month' => $month,
        'attitude_score' => $attitude,
        'sales_quantity' => $sales,
    ]);
}

it('exposes monthly performance with latest score, trend and delta', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level);

    recordScore($mentee, 2026, 4, 60, 40); // salesPct 40 → overall round((60+40)/2)=50
    recordScore($mentee, 2026, 5, 70, 60); // salesPct 60 → overall 65
    recordScore($mentee, 2026, 6, 90, 90); // salesPct 90 → overall 90

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.performance.has_scores', true)
            ->where('enrollment.performance.sales_target', 100)
            ->where('enrollment.performance.latest.overall', 90)
            ->where('enrollment.performance.latest.attitude', 90)
            ->where('enrollment.performance.latest.sales', 90)
            ->where('enrollment.performance.latest.sales_pct', 90)
            ->where('enrollment.performance.delta_overall', 25) // 90 - 65
            ->has('enrollment.performance.trend', 3)
            ->where('enrollment.stage_progress.total', 5)
        );
});

it('caps the sales percentage at 100 when sales exceed the target', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level);

    recordScore($mentee, 2026, 6, 80, 250); // salesPct capped 100 → overall round((80+100)/2)=90

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.performance.latest.sales_pct', 100)
            ->where('enrollment.performance.latest.overall', 90)
        );
});

it('falls back to attitude only when the level has no sales target', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => null, 'position' => 1]);
    $mentee = enrolHost($host, $level);

    recordScore($mentee, 2026, 6, 80, 50); // no target → salesPct null → overall = attitude 80

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.performance.sales_target', null)
            ->where('enrollment.performance.latest.sales_pct', null)
            ->where('enrollment.performance.latest.overall', 80)
        );
});

it('marks performance as empty when the host has no monthly scores', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    enrolHost($host);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.performance.has_scores', false)
            ->where('enrollment.performance.latest', null)
            ->has('enrollment.performance.trend', 0)
        );
});

it('omits the delta when there is only one month of scores', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level);

    recordScore($mentee, 2026, 6, 80, 80);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.performance.delta_overall', null)
            ->has('enrollment.performance.trend', 1)
        );
});

it('shows a graduated host their performance history', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level, 'graduated');

    recordScore($mentee, 2026, 6, 90, 90);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.status', 'graduated')
            ->where('enrollment.performance.has_scores', true)
        );
});

it('does not leak one host performance into another host props', function () {
    $hostA = User::factory()->create(['role' => 'live_host']);
    $hostB = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);

    $menteeA = enrolHost($hostA, $level);
    recordScore($menteeA, 2026, 6, 90, 90);
    enrolHost($hostB, $level); // host B has no scores

    $this->actingAs($hostB)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('enrollment.performance.has_scores', false));
});
