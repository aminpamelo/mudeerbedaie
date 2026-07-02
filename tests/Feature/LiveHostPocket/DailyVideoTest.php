<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A live_host with an active mentee enrollment. Returns [host, mentee].
 *
 * @return array{0: User, 1: LiveHostMentee}
 */
function videoHost(string $status = 'active'): array
{
    $host = User::factory()->create(['role' => 'live_host']);
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => $host->id,
        'status' => $status,
    ]);

    return [$host, $mentee];
}

it('logs a daily video with a title and optional link', function () {
    [$host, $mentee] = videoHost();

    $this->actingAs($host)
        ->post('/live-host/videos', ['title' => 'Skincare demo', 'link' => 'https://tiktok.com/@x/video/1'])
        ->assertRedirect();

    $row = LiveHostMenteeDailyVideo::where('mentee_id', $mentee->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->title)->toBe('Skincare demo')
        ->and($row->link)->toBe('https://tiktok.com/@x/video/1')
        ->and($row->video_date->toDateString())->toBe(today()->toDateString())
        ->and($row->logged_by)->toBe($host->id);
});

it('logs a daily video without a link', function () {
    [$host, $mentee] = videoHost();

    $this->actingAs($host)->post('/live-host/videos', ['title' => 'No-link video'])->assertRedirect();

    expect(LiveHostMenteeDailyVideo::where('mentee_id', $mentee->id)->value('link'))->toBeNull();
});

it('allows multiple videos on the same day', function () {
    [$host, $mentee] = videoHost();

    $this->actingAs($host)->post('/live-host/videos', ['title' => 'Morning clip']);
    $this->actingAs($host)->post('/live-host/videos', ['title' => 'Evening clip']);

    expect(LiveHostMenteeDailyVideo::where('mentee_id', $mentee->id)->count())->toBe(2);
});

it('requires a title', function () {
    [$host] = videoHost();

    $this->actingAs($host)->postJson('/live-host/videos', ['link' => 'https://example.com'])->assertStatus(422);
});

it('rejects an invalid link', function () {
    [$host] = videoHost();

    $this->actingAs($host)->postJson('/live-host/videos', ['title' => 'Bad link', 'link' => 'not-a-url'])->assertStatus(422);
});

it('forbids logging when the host is not an active mentee', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)->post('/live-host/videos', ['title' => 'Orphan'])->assertForbidden();

    expect(LiveHostMenteeDailyVideo::count())->toBe(0);
});

it('lets the owner delete their own video', function () {
    [$host, $mentee] = videoHost();
    $video = LiveHostMenteeDailyVideo::factory()->create([
        'mentee_id' => $mentee->id,
        'video_date' => today()->toDateString(),
    ]);

    $this->actingAs($host)->delete("/live-host/videos/{$video->id}")->assertRedirect();

    expect(LiveHostMenteeDailyVideo::find($video->id))->toBeNull();
});

it('forbids deleting another host video', function () {
    [$host] = videoHost();
    [, $otherMentee] = videoHost();
    $video = LiveHostMenteeDailyVideo::factory()->create(['mentee_id' => $otherMentee->id]);

    $this->actingAs($host)->delete("/live-host/videos/{$video->id}")->assertForbidden();

    expect(LiveHostMenteeDailyVideo::find($video->id))->not->toBeNull();
});

it('renders today videos, history and stats on the index page', function () {
    $this->travelTo(now()->startOfMonth()->addDays(14)->setTime(9, 0));
    [$host, $mentee] = videoHost();

    LiveHostMenteeDailyVideo::factory()->create([
        'mentee_id' => $mentee->id, 'video_date' => today()->toDateString(), 'title' => 'Today video',
    ]);
    LiveHostMenteeDailyVideo::factory()->create([
        'mentee_id' => $mentee->id, 'video_date' => today()->subDays(2)->toDateString(), 'title' => 'Older video',
    ]);

    $this->actingAs($host)
        ->get('/live-host/videos')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('DailyVideos', false)
            ->where('stats.logged_today', true)
            ->where('stats.month_videos', 2)
            ->where('stats.month_days', 2)
            ->has('today.videos', 1)
            ->has('history', 1)
        );
});

it('shows an empty state to a host who is not enrolled', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->get('/live-host/videos')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('DailyVideos', false)->where('enrollment', null));
});

it('surfaces the daily-video nudge on the Today screen', function () {
    [$host, $mentee] = videoHost();

    $this->actingAs($host)
        ->get('/live-host')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('videoLog.logged', false)->where('videoLog.count', 0));

    LiveHostMenteeDailyVideo::factory()->create([
        'mentee_id' => $mentee->id, 'video_date' => today()->toDateString(),
    ]);

    $this->actingAs($host)
        ->get('/live-host')
        ->assertInertia(fn (Assert $p) => $p->where('videoLog.logged', true)->where('videoLog.count', 1));
});
