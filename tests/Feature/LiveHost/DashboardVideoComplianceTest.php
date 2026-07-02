<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('reports today daily-video compliance across active mentees on the desk dashboard', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $program = LiveHostMentoringProgram::factory()->active()->create();

    $m1 = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'status' => 'active',
    ]);
    LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'status' => 'active',
    ]);

    // m1 posts today (twice); the second active mentee posts nothing.
    LiveHostMenteeDailyVideo::factory()->count(2)->create([
        'mentee_id' => $m1->id, 'video_date' => today()->toDateString(),
    ]);

    $this->actingAs($pic)
        ->get('/livehost')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->where('videoCompliance.active_mentees', 2)
            ->where('videoCompliance.posted', 1)
            ->where('videoCompliance.missing', 1)
            ->where('videoCompliance.videos_today', 2)
            ->where('videoCompliance.pct', 50)
        );
});
