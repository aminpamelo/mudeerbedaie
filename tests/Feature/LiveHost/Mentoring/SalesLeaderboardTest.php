<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function lbPic(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

function lbMentee(LiveHostMentoringProgram $program, ?int $mentorId = null): LiveHostMentee
{
    return LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'mentor_user_id' => $mentorId,
        'status' => 'active',
    ]);
}

function lbSession(int $hostId, string $datetime, float $gmv, float $adjustment = 0, string $status = 'ended'): void
{
    LiveSession::factory()->create([
        'live_host_id' => $hostId,
        'scheduled_start_at' => $datetime,
        'status' => $status,
        'gmv_amount' => $gmv,
        'gmv_adjustment' => $adjustment,
    ]);
}

const LB_URL = '/livehost/mentoring/leaderboard?scope=mentees&perf_year=2026&perf_from=5&perf_to=5';

it('ranks mentees by effective sales, highest first', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $top = lbMentee($program);
    $mid = lbMentee($program);

    lbSession($top->mentee_user_id, '2026-05-03 10:00:00', 2000);
    lbSession($top->mentee_user_id, '2026-05-04 10:00:00', 1000, 500); // effective 3500 total
    lbSession($mid->mentee_user_id, '2026-05-05 10:00:00', 900);

    $this->actingAs(lbPic())
        ->get(LB_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'mentees')
            ->has('hosts', 2)
            ->where('hosts.0.host_id', $top->mentee_user_id)
            ->where('hosts.0.sales', 3500)
            ->where('hosts.0.sessions', 2)
            ->where('hosts.0.is_mentee', true)
            ->where('hosts.1.host_id', $mid->mentee_user_id)
            ->where('hosts.1.sales', 900)
        );
});

it('applies a PIC daily override to the ranking total', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = lbMentee($program);
    lbSession($mentee->mentee_user_id, '2026-05-03 10:00:00', 1000);
    $mentee->dailyMetrics()->create(['metric_date' => '2026-05-03', 'sales_override' => 5000]);

    $this->actingAs(lbPic())
        ->get(LB_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hosts.0.host_id', $mentee->mentee_user_id)
            ->where('hosts.0.sales', 5000)
        );
});

it('surfaces the effective PIC (mentor override) with each host', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $pic = User::factory()->create(['role' => 'live_host', 'name' => 'Ustazah Kasma']);
    $mentee = lbMentee($program, $pic->id);
    lbSession($mentee->mentee_user_id, '2026-05-03 10:00:00', 700);

    $this->actingAs(lbPic())
        ->get(LB_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hosts.0.pic.id', $pic->id)
            ->where('hosts.0.pic.name', 'Ustazah Kasma')
        );
});

it('excludes non-mentee hosts in the mentees scope but includes them in the all scope', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = lbMentee($program);
    lbSession($mentee->mentee_user_id, '2026-05-03 10:00:00', 500);

    $loner = User::factory()->create(['role' => 'live_host']);
    lbSession($loner->id, '2026-05-04 10:00:00', 4000);

    // Mentees scope: only the enrolled mentee.
    $this->actingAs(lbPic())
        ->get('/livehost/mentoring/leaderboard?scope=mentees&perf_year=2026&perf_from=5&perf_to=5')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'mentees')
            ->has('hosts', 1)
            ->where('hosts.0.host_id', $mentee->mentee_user_id)
        );

    // All scope: both hosts, non-mentee flagged and using raw GMV.
    $this->actingAs(lbPic())
        ->get('/livehost/mentoring/leaderboard?scope=all&perf_year=2026&perf_from=5&perf_to=5')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'all')
            ->has('hosts', 2)
            ->where('hosts.0.host_id', $loner->id)
            ->where('hosts.0.sales', 4000)
            ->where('hosts.0.is_mentee', false)
            ->where('hosts.0.pic', null)
        );
});

it('honours the month window (sessions outside it do not count)', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = lbMentee($program);
    lbSession($mentee->mentee_user_id, '2026-05-03 10:00:00', 1200); // May only

    $this->actingAs(lbPic())
        ->get('/livehost/mentoring/leaderboard?scope=mentees&perf_year=2026&perf_from=6&perf_to=6')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('window.range.from', 6)
            ->where('window.range.to', 6)
            ->where('hosts.0.sales', 0)
            ->where('hosts.0.sessions', 0)
        );
});

it('counts only ended sessions, not scheduled ones', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = lbMentee($program);
    lbSession($mentee->mentee_user_id, '2026-05-03 10:00:00', 1000);
    lbSession($mentee->mentee_user_id, '2026-05-04 10:00:00', 9999, 0, 'scheduled'); // ignored

    $this->actingAs(lbPic())
        ->get(LB_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hosts.0.sales', 1000)
            ->where('hosts.0.sessions', 1)
        );
});

it('allows an admin to view the leaderboard', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(LB_URL)
        ->assertOk();
});

it('forbids a plain live host from viewing the leaderboard', function () {
    $this->actingAs(User::factory()->create(['role' => 'live_host']))
        ->get(LB_URL)
        ->assertForbidden();
});
