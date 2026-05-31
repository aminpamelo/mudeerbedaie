<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function mentoringPic(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

it('creates a program with a seeded 5-stage pipeline and redirects to edit', function () {
    $response = $this->actingAs(mentoringPic())
        ->post('/livehost/mentoring/programs', [
            'title' => 'Cohort June 2026',
        ]);

    $program = LiveHostMentoringProgram::where('title', 'Cohort June 2026')->first();
    expect($program)->not->toBeNull()
        ->and($program->status)->toBe('draft')
        ->and($program->slug)->toBe('cohort-june-2026')
        ->and($program->stages()->count())->toBe(5)
        ->and($program->stages()->where('is_final', true)->count())->toBe(1);

    $response->assertRedirectContains("/livehost/mentoring/programs/{$program->id}/edit");
});

it('requires a title to create a program', function () {
    $this->actingAs(mentoringPic())
        ->from('/livehost/mentoring/programs/create')
        ->post('/livehost/mentoring/programs', [])
        ->assertSessionHasErrors('title');
});

it('rejects a leader who is not a live host', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs(mentoringPic())
        ->from('/livehost/mentoring/programs/create')
        ->post('/livehost/mentoring/programs', [
            'title' => 'Bad leader',
            'leader_user_id' => $student->id,
        ])
        ->assertSessionHasErrors('leader_user_id');
});

it('activates a draft program that has a final stage', function () {
    $program = LiveHostMentoringProgram::factory()->create();

    $this->actingAs(mentoringPic())
        ->patch("/livehost/mentoring/programs/{$program->id}/activate")
        ->assertRedirect();

    expect($program->fresh()->status)->toBe('active');
});

it('refuses to activate a program with no final stage', function () {
    $program = LiveHostMentoringProgram::factory()->create();
    $program->stages()->update(['is_final' => false]);

    $this->actingAs(mentoringPic())
        ->patch("/livehost/mentoring/programs/{$program->id}/activate")
        ->assertStatus(422);

    expect($program->fresh()->status)->toBe('draft');
});

it('moves a program through pause and complete', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $pic = mentoringPic();

    $this->actingAs($pic)->patch("/livehost/mentoring/programs/{$program->id}/pause")->assertRedirect();
    expect($program->fresh()->status)->toBe('paused');

    $this->actingAs($pic)->patch("/livehost/mentoring/programs/{$program->id}/complete")->assertRedirect();
    expect($program->fresh()->status)->toBe('completed');
});

it('refuses to delete a program that has mentees', function () {
    $program = LiveHostMentoringProgram::factory()->create();
    LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
    ]);

    $this->actingAs(mentoringPic())
        ->delete("/livehost/mentoring/programs/{$program->id}")
        ->assertStatus(422);

    expect(LiveHostMentoringProgram::find($program->id))->not->toBeNull();
});

it('deletes an empty program', function () {
    $program = LiveHostMentoringProgram::factory()->create();

    $this->actingAs(mentoringPic())
        ->delete("/livehost/mentoring/programs/{$program->id}")
        ->assertRedirect();

    expect(LiveHostMentoringProgram::find($program->id))->toBeNull();
});

it('adds and enforces a single final stage in the editor', function () {
    $program = LiveHostMentoringProgram::factory()->create();
    $pic = mentoringPic();

    $this->actingAs($pic)
        ->post("/livehost/mentoring/programs/{$program->id}/stages", [
            'name' => 'Shadow Live',
            'is_final' => true,
        ])
        ->assertRedirect();

    expect($program->stages()->where('is_final', true)->count())->toBe(1)
        ->and($program->stages()->where('name', 'Shadow Live')->where('is_final', true)->exists())->toBeTrue();
});

it('blocks non-PIC roles from creating programs', function () {
    $this->actingAs(User::factory()->create(['role' => 'live_host']))
        ->get('/livehost/mentoring/programs')
        ->assertForbidden();
});
