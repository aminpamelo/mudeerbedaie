<?php

declare(strict_types=1);

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use App\Services\Recruitment\ApplicantStageTransition;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->campaign = LiveHostRecruitmentCampaign::factory()->create();
    $this->stageA = LiveHostRecruitmentStage::factory()->create([
        'campaign_id' => $this->campaign->id,
        'position' => 1,
        'name' => 'Review',
    ]);
    $this->stageB = LiveHostRecruitmentStage::factory()->create([
        'campaign_id' => $this->campaign->id,
        'position' => 2,
        'name' => 'Interview',
    ]);
});

it('opens the first stage row when the applicant has a current stage', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    LiveHostApplicantStage::query()->where('applicant_id', $applicant->id)->delete();

    app(ApplicantStageTransition::class)->enterFirstStage($applicant);

    $row = LiveHostApplicantStage::where('applicant_id', $applicant->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->stage_id)->toBe($this->stageA->id)
        ->and($row->exited_at)->toBeNull();
});

it('does not open a duplicate row if one is already open', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);

    app(ApplicantStageTransition::class)->enterFirstStage($applicant);

    expect(LiveHostApplicantStage::where('applicant_id', $applicant->id)->count())->toBe(1);
});

it('closes the old row and opens a new one on transition', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    app(ApplicantStageTransition::class)->enterFirstStage($applicant);

    app(ApplicantStageTransition::class)->transition($applicant, $this->stageB);

    $rows = LiveHostApplicantStage::where('applicant_id', $applicant->id)
        ->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->stage_id)->toBe($this->stageA->id)
        ->and($rows[0]->exited_at)->not->toBeNull()
        ->and($rows[1]->stage_id)->toBe($this->stageB->id)
        ->and($rows[1]->exited_at)->toBeNull();

    expect($applicant->fresh()->current_stage_id)->toBe($this->stageB->id);
});

it('clears assignee/due_at on stage change because new row starts blank', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    app(ApplicantStageTransition::class)->enterFirstStage($applicant);
    LiveHostApplicantStage::where('applicant_id', $applicant->id)
        ->whereNull('exited_at')
        ->update([
            'assignee_id' => \App\Models\User::factory()->create()->id,
            'due_at' => now()->addDays(3),
            'stage_notes' => 'discussed availability',
        ]);

    app(ApplicantStageTransition::class)->transition($applicant, $this->stageB);

    $current = LiveHostApplicantStage::where('applicant_id', $applicant->id)
        ->whereNull('exited_at')->first();

    expect($current->assignee_id)->toBeNull()
        ->and($current->due_at)->toBeNull()
        ->and($current->stage_notes)->toBeNull();
});

it('closes the open row on closeOpenRow', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    app(ApplicantStageTransition::class)->enterFirstStage($applicant);

    app(ApplicantStageTransition::class)->closeOpenRow($applicant);

    expect(
        LiveHostApplicantStage::where('applicant_id', $applicant->id)
            ->whereNull('exited_at')->count()
    )->toBe(0);
});
