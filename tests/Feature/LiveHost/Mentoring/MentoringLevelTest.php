<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\LevelSuggester;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function levelPic(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

it('seeds the default level ladder via migration', function () {
    expect(LiveHostMentoringLevel::count())->toBe(5)
        ->and(LiveHostMentoringLevel::where('is_top', true)->count())->toBe(1)
        ->and(LiveHostMentoringLevel::orderBy('position')->first()->name)->toBe('Rookie')
        ->and(LiveHostMentoringLevel::where('is_top', true)->first()->name)->toBe('Top Host');
});

it('suggests the entry level for zero activity', function () {
    $suggested = app(LevelSuggester::class)->suggest([
        'sessions' => 0, 'hours' => 0.0, 'gmv' => 0.0, 'attendancePct' => 0,
    ]);

    expect($suggested?->name)->toBe('Rookie');
});

it('suggests the top-host level for strong KPIs', function () {
    $suggested = app(LevelSuggester::class)->suggest([
        'sessions' => 50, 'hours' => 80.0, 'gmv' => 60000.0, 'attendancePct' => 95,
    ]);

    expect($suggested?->is_top)->toBeTrue()
        ->and($suggested?->name)->toBe('Top Host');
});

it('suggests a mid-tier level when only some thresholds are met', function () {
    // Meets Pro (16 / 28h / 10k / 80%) but not Elite (28 sessions).
    $suggested = app(LevelSuggester::class)->suggest([
        'sessions' => 18, 'hours' => 30.0, 'gmv' => 12000.0, 'attendancePct' => 82,
    ]);

    expect($suggested?->name)->toBe('Pro');
});

it('lets a PIC assign a level to a mentee and logs it', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'current_stage_id' => $program->stages()->orderBy('position')->first()->id,
    ]);
    $level = LiveHostMentoringLevel::where('name', 'Pro')->first();

    $this->actingAs(levelPic())
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/level", [
            'level_id' => $level->id,
            'source' => 'manual',
        ])
        ->assertRedirect();

    $mentee->refresh();
    expect($mentee->level_id)->toBe($level->id)
        ->and($mentee->level_source)->toBe('manual')
        ->and($mentee->level_assigned_at)->not->toBeNull()
        ->and($mentee->history()->where('action', 'leveled')->exists())->toBeTrue();
});

it('creates a new level at the end of the ladder', function () {
    $this->actingAs(levelPic())
        ->post('/livehost/mentoring/levels', [
            'name' => 'Legend',
            'color' => '#FF00FF',
            'min_sessions' => 60,
        ])
        ->assertRedirect();

    $level = LiveHostMentoringLevel::where('name', 'Legend')->first();
    expect($level)->not->toBeNull()
        ->and($level->slug)->toBe('legend')
        ->and($level->position)->toBe(6);
});

it('enforces a single top-host level', function () {
    $pic = levelPic();
    $this->actingAs($pic)->post('/livehost/mentoring/levels', ['name' => 'Apex', 'is_top' => true])->assertRedirect();

    expect(LiveHostMentoringLevel::where('is_top', true)->count())->toBe(1)
        ->and(LiveHostMentoringLevel::where('is_top', true)->first()->name)->toBe('Apex');
});

it('clears the mentee level when its level is deleted', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $level = LiveHostMentoringLevel::where('name', 'Pro')->first();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'level_id' => $level->id,
    ]);

    $this->actingAs(levelPic())
        ->delete("/livehost/mentoring/levels/{$level->id}")
        ->assertRedirect();

    expect(LiveHostMentoringLevel::find($level->id))->toBeNull()
        ->and($mentee->fresh()->level_id)->toBeNull();
});

it('blocks non-PIC roles from the levels catalog', function () {
    $this->actingAs(User::factory()->create(['role' => 'live_host']))
        ->get('/livehost/mentoring/levels')
        ->assertForbidden();
});
