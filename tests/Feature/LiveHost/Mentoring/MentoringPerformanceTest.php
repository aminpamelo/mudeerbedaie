<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyMetric;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMenteeDisciplinaryRecord;
use App\Models\LiveHostMenteeMonthlyScore;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

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

function perfSession(int $hostId, string $datetime, float $gmv, float $adjustment = 0, string $status = 'ended'): void
{
    LiveSession::factory()->create([
        'live_host_id' => $hostId,
        'scheduled_start_at' => $datetime,
        'status' => $status,
        'gmv_amount' => $gmv,
        'gmv_adjustment' => $adjustment,
    ]);
}

it('records a monthly attitude score and note for a mentee', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", [
            'year' => 2026, 'month' => 5, 'attitude_score' => 82, 'notes' => 'Strong month',
        ])
        ->assertRedirect();

    $row = LiveHostMenteeMonthlyScore::where('mentee_id', $mentee->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->year)->toBe(2026)
        ->and($row->month)->toBe(5)
        ->and($row->attitude_score)->toBe(82)
        ->and($row->notes)->toBe('Strong month');
});

it('sums effective daily live-session GMV into the monthly sales cell', function () {
    $mentee = perfMentee();
    $hostId = $mentee->mentee_user_id;

    perfSession($hostId, '2026-05-03 10:00:00', 1000);
    perfSession($hostId, '2026-05-04 10:00:00', 1500, 200); // effective 1700
    perfSession($hostId, '2026-05-10 10:00:00', 9999, 0, 'scheduled'); // not ended → ignored

    $this->actingAs(perfPic())
        ->get("/livehost/mentoring/programs/{$mentee->program_id}/edit?perf_year=2026&perf_from=5&perf_to=5")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('performance.mentees.0.scores.2026-05.sales', 2700)
        );
});

it('lets a PIC override a single day, changing the monthly sum', function () {
    $mentee = perfMentee();
    $hostId = $mentee->mentee_user_id;
    perfSession($hostId, '2026-05-03 10:00:00', 1000);

    $this->actingAs(perfPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/daily-metric", [
            'date' => '2026-05-03', 'comment' => 'Coached mid-live', 'sales_override' => 4000,
        ])
        ->assertRedirect();

    $this->actingAs(perfPic())
        ->get("/livehost/mentoring/programs/{$mentee->program_id}/edit?perf_year=2026&perf_from=5&perf_to=5")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('performance.mentees.0.scores.2026-05.sales', 4000));
});

it('returns a daily matrix for an expanded month', function () {
    $mentee = perfMentee();
    $hostId = $mentee->mentee_user_id;
    perfSession($hostId, '2026-05-03 10:00:00', 1000);
    $mentee->dailyMetrics()->create([
        'metric_date' => '2026-05-04', 'comment' => 'good day', 'sales_override' => 500, 'commented_at' => now(),
    ]);
    $mentee->disciplinaryRecords()->create([
        'incident_date' => '2026-05-05', 'category' => 'lateness', 'severity' => 'minor', 'description' => 'Late',
    ]);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/programs/{$mentee->program_id}/daily-matrix?year=2026&month=5")
        ->assertOk()
        ->json();

    expect($res['days_in_month'])->toBe(31);
    $days = $res['by_mentee'][$mentee->id];
    expect((float) $days[2]['effective'])->toBe(1000.0)     // day 3: auto GMV
        ->and((float) $days[3]['effective'])->toBe(500.0)    // day 4: PIC override
        ->and($days[3]['has_comment'])->toBeTrue()
        ->and($days[4]['has_disciplinary'])->toBeTrue()      // day 5: disciplinary
        ->and($days[3]['has_disciplinary'])->toBeFalse();
});

it('includes daily-video status in the daily matrix', function () {
    $mentee = perfMentee();
    LiveHostMenteeDailyVideo::factory()->count(2)->create(['mentee_id' => $mentee->id, 'video_date' => '2026-05-04']);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/programs/{$mentee->program_id}/daily-matrix?year=2026&month=5")
        ->assertOk()
        ->json();

    $days = $res['by_mentee'][$mentee->id];
    expect($days[3]['has_video'])->toBeTrue()          // day 4
        ->and($days[3]['video_count'])->toBe(2)
        ->and($days[2]['has_video'])->toBeFalse();      // day 3
});

it('includes video status, links and a missing-video count in the daily log', function () {
    $mentee = perfMentee();
    LiveHostMenteeDailyVideo::factory()->create([
        'mentee_id' => $mentee->id, 'video_date' => '2026-05-03', 'title' => 'Hook demo', 'link' => 'https://tiktok.com/x',
    ]);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/programs/{$mentee->program_id}/daily-log?date=2026-05-03")
        ->assertOk()
        ->json();

    expect($res['mentees'][0]['has_video'])->toBeTrue()
        ->and($res['mentees'][0]['video_count'])->toBe(1)
        ->and($res['mentees'][0]['videos'][0]['title'])->toBe('Hook demo')
        ->and($res['mentees'][0]['videos'][0]['link'])->toBe('https://tiktok.com/x')
        ->and($res['missing_video'])->toBe(0);
});

it('reports a missing video in the daily log when none logged', function () {
    $mentee = perfMentee();

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/programs/{$mentee->program_id}/daily-log?date=2026-05-03")
        ->assertOk()
        ->json();

    expect($res['mentees'][0]['has_video'])->toBeFalse()
        ->and($res['missing_video'])->toBe(1);
});

it('includes videos in the day detail', function () {
    $mentee = perfMentee();
    LiveHostMenteeDailyVideo::factory()->create(['mentee_id' => $mentee->id, 'video_date' => '2026-05-03', 'title' => 'Day video']);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/mentees/{$mentee->id}/day-detail?date=2026-05-03")
        ->assertOk()
        ->json();

    expect($res['videos'])->toHaveCount(1)
        ->and($res['videos'][0]['title'])->toBe('Day video');
});

it('includes video totals and per-day videos in the month overview', function () {
    $mentee = perfMentee();
    LiveHostMenteeDailyVideo::factory()->count(2)->create(['mentee_id' => $mentee->id, 'video_date' => '2026-05-03']);
    LiveHostMenteeDailyVideo::factory()->create(['mentee_id' => $mentee->id, 'video_date' => '2026-05-06']);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/mentees/{$mentee->id}/month-overview?year=2026&month=5")
        ->assertOk()
        ->json();

    expect($res['summary']['video_total'])->toBe(3)
        ->and($res['summary']['video_days'])->toBe(2);

    $day3 = collect($res['days'])->firstWhere('date', '2026-05-03');
    expect($day3['videos'])->toHaveCount(2)
        ->and($day3['has_activity'])->toBeTrue();
});

it('returns full day detail — sessions, disciplinary and the comment', function () {
    $mentee = perfMentee();
    $hostId = $mentee->mentee_user_id;
    perfSession($hostId, '2026-05-03 10:00:00', 1200);
    $mentee->dailyMetrics()->create(['metric_date' => '2026-05-03', 'comment' => 'Solid open', 'commented_at' => now()]);
    $mentee->disciplinaryRecords()->create(['incident_date' => '2026-05-03', 'category' => 'lateness', 'severity' => 'minor', 'description' => 'Late']);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/mentees/{$mentee->id}/day-detail?date=2026-05-03")
        ->assertOk()
        ->json();

    expect($res['sessions'])->toHaveCount(1)
        ->and((float) $res['sessions'][0]['gmv'])->toBe(1200.0)
        ->and($res['disciplinary'])->toHaveCount(1)
        ->and($res['disciplinary'][0]['category'])->toBe('lateness')
        ->and($res['metric']['comment'])->toBe('Solid open');
});

it('returns the daily log with compliance count and per-host disciplinary counts', function () {
    $mentee = perfMentee();
    $mentee->disciplinaryRecords()->create(['incident_date' => '2026-05-03', 'category' => 'lateness', 'severity' => 'minor', 'description' => 'Late']);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/programs/{$mentee->program_id}/daily-log?date=2026-05-03")
        ->assertOk()
        ->json();

    expect($res['missing'])->toBe(1)
        ->and($res['mentees'][0]['has_disciplinary'])->toBeTrue()
        ->and($res['mentees'][0]['disciplinary_count'])->toBe(1);
});

it('returns a full month overview — day-by-day sessions, comments and disciplinary', function () {
    $mentee = perfMentee();
    $hostId = $mentee->mentee_user_id;
    perfSession($hostId, '2026-05-03 10:00:00', 1200);
    $mentee->dailyMetrics()->create(['metric_date' => '2026-05-03', 'comment' => 'Great open', 'commented_at' => now()]);
    $mentee->disciplinaryRecords()->create(['incident_date' => '2026-05-05', 'category' => 'lateness', 'severity' => 'minor', 'description' => 'Late']);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/mentees/{$mentee->id}/month-overview?year=2026&month=5")
        ->assertOk()
        ->json();

    expect($res['summary']['sales_total'])->toEqual(1200)
        ->and($res['summary']['live_days'])->toBe(1)
        ->and($res['summary']['comment_days'])->toBe(1)
        ->and($res['summary']['disciplinary_total'])->toBe(1)
        ->and($res['days'])->toHaveCount(31);

    $day3 = collect($res['days'])->firstWhere('date', '2026-05-03');
    expect($day3['sessions'])->toHaveCount(1)
        ->and($day3['comment'])->toBe('Great open')
        ->and($day3['has_activity'])->toBeTrue();

    $day5 = collect($res['days'])->firstWhere('date', '2026-05-05');
    expect($day5['disciplinary'])->toHaveCount(1);
});

it('requires a comment when logging a daily metric', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patchJson("/livehost/mentoring/mentees/{$mentee->id}/daily-metric", ['date' => '2026-05-03', 'sales_override' => 500])
        ->assertStatus(422);
});

it('stores a daily comment and sales override', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/daily-metric", [
            'date' => '2026-05-03', 'comment' => 'Great hooks', 'sales_override' => 640.5,
        ])
        ->assertRedirect();

    $row = LiveHostMenteeDailyMetric::where('mentee_id', $mentee->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->comment)->toBe('Great hooks')
        ->and((float) $row->sales_override)->toBe(640.5);
});

it('updates the same day on re-save instead of violating the unique constraint', function () {
    $mentee = perfMentee();
    $url = "/livehost/mentoring/mentees/{$mentee->id}/daily-metric";

    $this->actingAs(perfPic())
        ->patch($url, ['date' => '2026-05-03', 'comment' => 'First note', 'sales_override' => 100])
        ->assertRedirect();

    $this->actingAs(perfPic())
        ->patch($url, ['date' => '2026-05-03', 'comment' => 'Updated note', 'sales_override' => 250])
        ->assertRedirect();

    $rows = LiveHostMenteeDailyMetric::where('mentee_id', $mentee->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->comment)->toBe('Updated note')
        ->and((float) $rows->first()->sales_override)->toBe(250.0);
});

it('renames the host account from the performance board', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/name", ['name' => 'Renamed Host'])
        ->assertRedirect();

    expect($mentee->menteeUser->fresh()->name)->toBe('Renamed Host');
});

it('assigns a per-host PIC (mentor override)', function () {
    $mentee = perfMentee();
    $newPic = User::factory()->create(['role' => 'live_host']);

    $this->actingAs(perfPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/pic", ['mentor_user_id' => $newPic->id])
        ->assertRedirect();

    expect($mentee->fresh()->mentor_user_id)->toBe($newPic->id);
});

it('records, lists and removes a disciplinary entry', function () {
    $mentee = perfMentee();
    $pic = perfPic();

    $this->actingAs($pic)
        ->post("/livehost/mentoring/mentees/{$mentee->id}/disciplinary", [
            'incident_date' => '2026-05-03', 'category' => 'lateness', 'severity' => 'minor', 'description' => 'Late by 10 minutes',
        ])
        ->assertRedirect();

    $record = LiveHostMenteeDisciplinaryRecord::where('mentee_id', $mentee->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->category)->toBe('lateness')
        ->and($record->severity)->toBe('minor');

    $this->actingAs($pic)
        ->delete("/livehost/mentoring/disciplinary/{$record->id}")
        ->assertRedirect();

    expect(LiveHostMenteeDisciplinaryRecord::count())->toBe(0);
});

it('lists a mentee disciplinary records as JSON for the modal', function () {
    $mentee = perfMentee();
    $mentee->disciplinaryRecords()->create([
        'incident_date' => '2026-05-03', 'category' => 'lateness', 'severity' => 'minor', 'description' => 'Late',
    ]);

    $res = $this->actingAs(perfPic())
        ->getJson("/livehost/mentoring/mentees/{$mentee->id}/disciplinary")
        ->assertOk()
        ->json();

    expect($res['records'])->toHaveCount(1)
        ->and($res['records'][0]['category'])->toBe('lateness');
});

it('validates the disciplinary category and severity', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->postJson("/livehost/mentoring/mentees/{$mentee->id}/disciplinary", [
            'incident_date' => '2026-05-03', 'category' => 'bogus', 'severity' => 'minor', 'description' => 'x',
        ])
        ->assertStatus(422);
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

it('rejects out-of-range attitude and month values', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->patchJson("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'attitude_score' => 150])
        ->assertStatus(422);

    $this->actingAs(perfPic())
        ->patchJson("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 13, 'attitude_score' => 50])
        ->assertStatus(422);
});

it('honours the month filter window (year + from/to)', function () {
    $mentee = perfMentee();

    $this->actingAs(perfPic())
        ->get("/livehost/mentoring/programs/{$mentee->program_id}/edit?perf_year=2026&perf_from=1&perf_to=12")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('performance.months', 12)
            ->where('performance.year', 2026)
            ->where('performance.months.0.value', '2026-01')
            ->where('performance.months.0.month', 1)
            ->where('performance.months.11.month', 12)
            ->has('performance.mentees', 1)
            ->where('performance.mentees.0.id', $mentee->id)
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
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/monthly-score", ['year' => 2026, 'month' => 5, 'attitude_score' => 50])
        ->assertForbidden();
});
