<?php

declare(strict_types=1);

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeStage;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveHostMentoringStage;
use App\Models\User;
use App\Services\Mentoring\MenteeStageTransition;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->program = LiveHostMentoringProgram::factory()->create();
    $this->stageA = LiveHostMentoringStage::factory()->create([
        'program_id' => $this->program->id,
        'position' => 1,
        'name' => 'Onboarding',
    ]);
    $this->stageB = LiveHostMentoringStage::factory()->create([
        'program_id' => $this->program->id,
        'position' => 2,
        'name' => 'Coaching',
    ]);
});

it('opens the first stage row when the mentee has a current stage', function () {
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $this->program->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    LiveHostMenteeStage::query()->where('mentee_id', $mentee->id)->delete();

    app(MenteeStageTransition::class)->enterFirstStage($mentee);

    $row = LiveHostMenteeStage::where('mentee_id', $mentee->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->stage_id)->toBe($this->stageA->id)
        ->and($row->exited_at)->toBeNull();
});

it('opens the first stage row with the program leader as default assignee', function () {
    $leader = User::factory()->create(['role' => 'live_host']);
    $this->program->update(['leader_user_id' => $leader->id]);

    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $this->program->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    LiveHostMenteeStage::query()->where('mentee_id', $mentee->id)->delete();

    app(MenteeStageTransition::class)->enterFirstStage($mentee->fresh());

    $row = LiveHostMenteeStage::where('mentee_id', $mentee->id)->whereNull('exited_at')->first();
    expect($row->assignee_id)->toBe($leader->id);
});

it('does not open a duplicate row if one is already open', function () {
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $this->program->id,
        'current_stage_id' => $this->stageA->id,
    ]);

    app(MenteeStageTransition::class)->enterFirstStage($mentee);
    app(MenteeStageTransition::class)->enterFirstStage($mentee);

    expect(LiveHostMenteeStage::where('mentee_id', $mentee->id)->count())->toBe(1);
});

it('closes the old row and opens a new one on transition', function () {
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $this->program->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    app(MenteeStageTransition::class)->enterFirstStage($mentee);

    app(MenteeStageTransition::class)->transition($mentee, $this->stageB);

    $rows = LiveHostMenteeStage::where('mentee_id', $mentee->id)
        ->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->stage_id)->toBe($this->stageA->id)
        ->and($rows[0]->exited_at)->not->toBeNull()
        ->and($rows[1]->stage_id)->toBe($this->stageB->id)
        ->and($rows[1]->exited_at)->toBeNull();

    expect($mentee->fresh()->current_stage_id)->toBe($this->stageB->id);
});

it('closes the open row on closeOpenRow', function () {
    $mentee = LiveHostMentee::factory()->create([
        'program_id' => $this->program->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    app(MenteeStageTransition::class)->enterFirstStage($mentee);

    app(MenteeStageTransition::class)->closeOpenRow($mentee);

    expect(
        LiveHostMenteeStage::where('mentee_id', $mentee->id)
            ->whereNull('exited_at')->count()
    )->toBe(0);
});

it('seeds five default stages when a program is created', function () {
    $program = LiveHostMentoringProgram::factory()->create();

    $stages = $program->stages()->orderBy('position')->get();

    expect($stages)->toHaveCount(5)
        ->and($stages->pluck('name')->all())->toBe(['Onboarding', 'Coaching', 'Training', 'Evaluation', 'Graduated'])
        ->and($stages->where('is_final', true)->count())->toBe(1)
        ->and($stages->last()->is_final)->toBeTrue();
});

it('generates sequential mentee numbers for the current month', function () {
    $first = LiveHostMentee::generateMenteeNumber();
    $program = LiveHostMentoringProgram::factory()->create();
    LiveHostMentee::factory()->create([
        'program_id' => $program->id,
        'mentee_number' => $first,
    ]);
    $second = LiveHostMentee::generateMenteeNumber();

    $prefix = 'LHM-'.now()->format('Ym').'-';
    expect($first)->toBe($prefix.'0001')
        ->and($second)->toBe($prefix.'0002');
});
