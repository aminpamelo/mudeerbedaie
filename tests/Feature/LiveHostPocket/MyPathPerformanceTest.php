<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeMonthlyScore;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

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

/**
 * Seed one month of performance: the PIC's monthly attitude score, plus an ended
 * live session whose GMV becomes that month's (daily-summed) sales figure.
 */
function seedMonth(LiveHostMentee $mentee, User $host, CarbonImmutable $anchor, ?int $attitude, ?float $gmv): void
{
    if ($attitude !== null) {
        LiveHostMenteeMonthlyScore::create([
            'mentee_id' => $mentee->id,
            'year' => (int) $anchor->format('Y'),
            'month' => (int) $anchor->format('n'),
            'attitude_score' => $attitude,
        ]);
    }

    if ($gmv !== null) {
        LiveSession::factory()->create([
            'live_host_id' => $host->id,
            'scheduled_start_at' => $anchor->startOfMonth()->addDays(14)->setTime(10, 0),
            'status' => 'ended',
            'gmv_amount' => $gmv,
            'gmv_adjustment' => 0,
        ]);
    }
}

it('exposes monthly performance with latest score, trend and delta', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level);

    $m0 = CarbonImmutable::now()->startOfMonth();
    seedMonth($mentee, $host, $m0->subMonths(2), 60, 40); // salesPct 40 → overall 50
    seedMonth($mentee, $host, $m0->subMonth(), 70, 60);   // salesPct 60 → overall 65
    seedMonth($mentee, $host, $m0, 90, 90);               // salesPct 90 → overall 90

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
            ->has('enrollment.performance.trend', 6)            // fixed 6-month window
            ->where('enrollment.stage_progress.total', 5)
        );
});

it('caps the sales percentage at 100 when sales exceed the target', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level);

    seedMonth($mentee, $host, CarbonImmutable::now()->startOfMonth(), 80, 250); // capped 100 → overall 90

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

    seedMonth($mentee, $host, CarbonImmutable::now()->startOfMonth(), 80, 50); // no target → salesPct null → overall 80

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.performance.sales_target', null)
            ->where('enrollment.performance.latest.sales_pct', null)
            ->where('enrollment.performance.latest.overall', 80)
        );
});

it('marks performance as empty when the host has no scores or sales', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    enrolHost($host);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.performance.has_scores', false)
            ->where('enrollment.performance.latest', null)
            ->has('enrollment.performance.trend', 6)
        );
});

it('omits the delta when there is only one month of data', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level);

    seedMonth($mentee, $host, CarbonImmutable::now()->startOfMonth(), 80, 80);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.performance.delta_overall', null)
            ->has('enrollment.performance.trend', 6)
        );
});

it('surfaces the daily sales strip, PIC comments and conduct records', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level);

    $today = CarbonImmutable::now();
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'scheduled_start_at' => $today->startOfDay()->setTime(10, 0),
        'status' => 'ended',
        'gmv_amount' => 500,
        'gmv_adjustment' => 0,
    ]);
    $mentee->dailyMetrics()->create([
        'metric_date' => $today->toDateString(),
        'comment' => 'Nice energy today',
        'commented_by' => $host->id,
        'commented_at' => now(),
    ]);
    $mentee->disciplinaryRecords()->create([
        'incident_date' => $today->toDateString(),
        'category' => 'lateness',
        'severity' => 'minor',
        'description' => 'Started 15m late',
    ]);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.daily.total', 500)
            ->has('enrollment.comments', 1)
            ->where('enrollment.comments.0.comment', 'Nice energy today')
            ->has('enrollment.conduct', 1)
            ->where('enrollment.conduct.0.category', 'lateness')
        );
});

it('shows a graduated host their performance history', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolHost($host, $level, 'graduated');

    seedMonth($mentee, $host, CarbonImmutable::now()->startOfMonth(), 90, 90);

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
    seedMonth($menteeA, $hostA, CarbonImmutable::now()->startOfMonth(), 90, 90);
    enrolHost($hostB, $level); // host B has no scores

    $this->actingAs($hostB)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('enrollment.performance.has_scores', false));
});

/**
 * Enrol two hosts into the SAME program (a cohort), so the leaderboard has
 * more than one row to rank.
 *
 * @return array{0: LiveHostMentee, 1: LiveHostMentee}
 */
function enrolCohort(User $me, User $peer, ?LiveHostMentoringLevel $level, CarbonImmutable $enrolledAt): array
{
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $stageId = $program->stages()->orderBy('position')->first()->id;

    $make = fn (User $u) => LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $u->id,
        'current_stage_id' => $stageId,
        'level_id' => $level?->id,
        'status' => 'active',
        'enrolled_at' => $enrolledAt,
    ]);

    return [$make($me), $make($peer)];
}

it('ranks the program cohort by this-month sales and marks the current host', function () {
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 1000, 'position' => 1]);
    $me = User::factory()->create(['role' => 'live_host']);
    $peer = User::factory()->create(['role' => 'live_host']);

    [$meMentee, $peerMentee] = enrolCohort($me, $peer, $level, CarbonImmutable::now());

    $anchor = CarbonImmutable::now()->startOfMonth();
    seedMonth($meMentee, $me, $anchor, null, 300);     // me: RM 300
    seedMonth($peerMentee, $peer, $anchor, null, 800);  // peer: RM 800 → leads

    // A mentee in a different program must never appear on my cohort board.
    $outsiderHost = User::factory()->create(['role' => 'live_host']);
    $outsider = enrolHost($outsiderHost, $level);
    seedMonth($outsider, $outsiderHost, $anchor, null, 5000);

    $this->actingAs($me)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaderboard.member_count', 2)
            ->where('leaderboard.my_mentee_id', $meMentee->id)
            ->has('leaderboard.periods.this_month.rows', 2)
            ->where('leaderboard.periods.this_month.rows.0.name', $peer->name)
            ->where('leaderboard.periods.this_month.rows.0.rank', 1)
            ->where('leaderboard.periods.this_month.rows.0.is_me', false)
            ->where('leaderboard.periods.this_month.rows.1.name', $me->name)
            ->where('leaderboard.periods.this_month.rows.1.rank', 2)
            ->where('leaderboard.periods.this_month.rows.1.is_me', true)
        );
});

it('aggregates all-time sales across months, distinct from this month', function () {
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 1000, 'position' => 1]);
    $me = User::factory()->create(['role' => 'live_host']);
    $peer = User::factory()->create(['role' => 'live_host']);

    // Enrolled two months ago so "all time" spans last month too.
    [$meMentee, $peerMentee] = enrolCohort($me, $peer, $level, CarbonImmutable::now()->subMonths(2));

    $thisMonth = CarbonImmutable::now()->startOfMonth();
    seedMonth($meMentee, $me, $thisMonth, null, 100);            // me: 100 this month
    seedMonth($meMentee, $me, $thisMonth->subMonth(), null, 900); // + 900 last month = 1000 all-time
    seedMonth($peerMentee, $peer, $thisMonth, null, 500);        // peer: 500 this month & all-time

    $this->actingAs($me)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // This month: peer (500) leads me (100).
            ->where('leaderboard.periods.this_month.rows.0.is_me', false)
            ->where('leaderboard.periods.this_month.rows.1.is_me', true)
            // All time: me (1000) overtakes peer (500).
            ->where('leaderboard.periods.all_time.rows.0.is_me', true)
            ->where('leaderboard.periods.all_time.rows.1.is_me', false)
        );
});

it('returns a null leaderboard for a host not in any program', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->get('/live-host/my-path')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment', null)
            ->where('leaderboard', null)
        );
});
