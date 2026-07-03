<?php

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeMonthlyScore;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
});

/** Enrol a host into a fresh active program at its first stage, with the given level. */
function enrolTodayHost(User $host, ?LiveHostMentoringLevel $level = null): LiveHostMentee
{
    $program = LiveHostMentoringProgram::factory()->active()->create();

    return LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'current_stage_id' => $program->stages()->orderBy('position')->first()->id,
        'level_id' => $level?->id,
        'status' => 'active',
        'enrolled_at' => CarbonImmutable::now(),
    ]);
}

/** Seed one month: the PIC's attitude score plus an ended session whose GMV is that month's sales. */
function seedTodayMonth(LiveHostMentee $mentee, User $host, CarbonImmutable $anchor, ?int $attitude, ?float $gmv): void
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

it('shows today stats with sessions, liveNow, upcoming', function () {
    LiveSession::factory()->count(2)->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
        'scheduled_start_at' => now()->startOfDay()->addHours(9),
        'actual_end_at' => now(),
        'duration_minutes' => 90,
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'live',
        'scheduled_start_at' => now()->startOfDay()->addHours(12),
        'actual_start_at' => now()->subHours(2),
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addHours(3),
    ]);

    actingAs($this->host)
        ->get('/live-host')
        ->assertInertia(fn (Assert $p) => $p
            ->component('Today', false)
            ->has('stats', fn (Assert $s) => $s
                ->where('sessionsDoneToday', 2)
                ->where('watchMinutesToday', 180)
                ->etc())
            ->has('liveNow', 1)
            ->has('upcoming', 1));
});

it('omits other hosts sessions', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    LiveSession::factory()->count(3)->create([
        'live_host_id' => $otherHost->id,
        'status' => 'live',
        'actual_start_at' => now()->subHour(),
    ]);

    actingAs($this->host)
        ->get('/live-host')
        ->assertInertia(fn (Assert $p) => $p->has('liveNow', 0));
});

it('ends a live session', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'live',
        'actual_start_at' => now()->subHour(),
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$session->id}/end")
        ->assertRedirect('/live-host');

    expect($session->fresh())
        ->status->toBe('ended')
        ->actual_end_at->not->toBeNull();
});

it('forbids ending another hosts session', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create([
        'live_host_id' => $otherHost->id,
        'status' => 'live',
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$session->id}/end")
        ->assertForbidden();
});

it('rejects end-session when status is not live', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$session->id}/end")
        ->assertStatus(409);
});

it('has no performance summary for a host outside any mentoring program', function () {
    actingAs($this->host)
        ->get('/live-host')
        ->assertInertia(fn (Assert $p) => $p->where('performanceSummary', null));
});

it('summarises the latest score, delta and this-month sales for an enrolled host', function () {
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 100, 'position' => 1]);
    $mentee = enrolTodayHost($this->host, $level);

    $m0 = CarbonImmutable::now()->startOfMonth();
    seedTodayMonth($mentee, $this->host, $m0->subMonth(), 70, 60); // salesPct 60 → overall 65
    seedTodayMonth($mentee, $this->host, $m0, 90, 90);             // salesPct 90 → overall 90

    actingAs($this->host)
        ->get('/live-host')
        ->assertInertia(fn (Assert $p) => $p
            ->has('performanceSummary', fn (Assert $s) => $s
                ->where('score', 90)
                ->where('score_delta', 25) // 90 - 65
                ->where('sales_month', 90)
                ->where('sales_target', 100)
                ->where('sales_pct', 90)
                ->where('rank', null)      // solo cohort — nobody to rank against
                ->where('cohort_size', 1)
                ->has('trend', 6)));
});

it('ranks the host within their program cohort on the today summary', function () {
    $level = LiveHostMentoringLevel::factory()->create(['monthly_sales_target' => 1000, 'position' => 1]);
    $peerHost = User::factory()->create(['role' => 'live_host']);

    $program = LiveHostMentoringProgram::factory()->active()->create();
    $stageId = $program->stages()->orderBy('position')->first()->id;
    $make = fn (User $u) => LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $u->id,
        'current_stage_id' => $stageId,
        'level_id' => $level->id,
        'status' => 'active',
        'enrolled_at' => CarbonImmutable::now(),
    ]);
    $meMentee = $make($this->host);
    $peerMentee = $make($peerHost);

    $anchor = CarbonImmutable::now()->startOfMonth();
    seedTodayMonth($meMentee, $this->host, $anchor, null, 300);   // me: RM 300
    seedTodayMonth($peerMentee, $peerHost, $anchor, null, 800);   // peer: RM 800 → leads

    actingAs($this->host)
        ->get('/live-host')
        ->assertInertia(fn (Assert $p) => $p
            ->where('performanceSummary.rank', 2)
            ->where('performanceSummary.cohort_size', 2)
            ->where('performanceSummary.sales_month', 300));
});
