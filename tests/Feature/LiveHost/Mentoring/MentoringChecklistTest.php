<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeChecklistItem;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function checklistPic(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

it('seeds a default checklist template when a program is created', function () {
    $program = LiveHostMentoringProgram::factory()->create();

    expect($program->checklist_template)->toBeArray()
        ->and(count($program->checklist_template))->toBe(6)
        ->and($program->checklist_template[0])->toHaveKeys(['title', 'is_required']);
});

it('copies the program template onto a mentee at enrolment', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create([
        'checklist_template' => [
            ['title' => 'Task A', 'is_required' => true],
            ['title' => 'Task B', 'is_required' => false],
        ],
    ]);
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs(checklistPic())
        ->post("/livehost/mentoring/programs/{$program->id}/mentees", ['mentee_user_id' => $host->id])
        ->assertRedirect();

    $mentee = LiveHostMentee::where('mentee_user_id', $host->id)->first();
    expect($mentee->checklistItems()->count())->toBe(2)
        ->and($mentee->checklistItems()->orderBy('position')->first()->title)->toBe('Task A')
        ->and($mentee->checklistItems()->where('title', 'Task B')->first()->is_required)->toBeFalse();
});

it('toggles a checklist item done and back', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
    ]);
    $item = LiveHostMenteeChecklistItem::factory()->create(['mentee_id' => $mentee->id]);
    $pic = checklistPic();

    // Redirect (not 204): the board toggles via Inertia's router.patch, and a 204
    // makes Inertia render a blank white modal instead of applying the change.
    $this->actingAs($pic)
        ->from("/livehost/mentoring/programs/{$program->id}/edit")
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/checklist/{$item->id}/toggle")
        ->assertRedirect();
    $item->refresh();
    expect($item->status)->toBe('done')->and($item->completed_at)->not->toBeNull();

    $this->actingAs($pic)
        ->from("/livehost/mentoring/programs/{$program->id}/edit")
        ->patch("/livehost/mentoring/mentees/{$mentee->id}/checklist/{$item->id}/toggle")
        ->assertRedirect();
    $item->refresh();
    expect($item->status)->toBe('pending')->and($item->completed_at)->toBeNull();
});

it('adds and removes a per-mentee checklist item', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id,
    ]);
    $pic = checklistPic();

    $this->actingAs($pic)
        ->post("/livehost/mentoring/mentees/{$mentee->id}/checklist", ['title' => 'Extra coaching'])
        ->assertRedirect();
    $item = $mentee->checklistItems()->where('title', 'Extra coaching')->first();
    expect($item)->not->toBeNull();

    $this->actingAs($pic)
        ->delete("/livehost/mentoring/mentees/{$mentee->id}/checklist/{$item->id}")
        ->assertRedirect();
    expect(LiveHostMenteeChecklistItem::find($item->id))->toBeNull();
});

it('persists an edited checklist template on the program', function () {
    $program = LiveHostMentoringProgram::factory()->create();

    $this->actingAs(checklistPic())
        ->put("/livehost/mentoring/programs/{$program->id}", [
            'title' => $program->title,
            'slug' => $program->slug,
            'checklist_template' => [
                ['title' => 'New onboarding step', 'is_required' => true],
            ],
        ])
        ->assertRedirect();

    expect($program->fresh()->checklist_template)->toBe([
        ['title' => 'New onboarding step', 'is_required' => true],
    ]);
});

it('blocks a checklist item toggle across mentees', function () {
    $program = LiveHostMentoringProgram::factory()->active()->create();
    $menteeA = LiveHostMentee::factory()->create(['program_id' => $program->id, 'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id]);
    $menteeB = LiveHostMentee::factory()->create(['program_id' => $program->id, 'mentee_user_id' => User::factory()->create(['role' => 'live_host'])->id]);
    $itemB = LiveHostMenteeChecklistItem::factory()->create(['mentee_id' => $menteeB->id]);

    $this->actingAs(checklistPic())
        ->patch("/livehost/mentoring/mentees/{$menteeA->id}/checklist/{$itemB->id}/toggle")
        ->assertNotFound();
});
