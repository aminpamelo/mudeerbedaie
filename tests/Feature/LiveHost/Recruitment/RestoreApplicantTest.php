<?php

declare(strict_types=1);

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use App\Models\User;
use App\Services\Recruitment\ApplicantStageTransition;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin_livehost']);
    $this->campaign = LiveHostRecruitmentCampaign::factory()->create();
    $this->stage = LiveHostRecruitmentStage::factory()->create([
        'campaign_id' => $this->campaign->id, 'position' => 1, 'name' => 'Review',
    ]);
    $this->applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stage->id,
        'status' => 'rejected',
    ]);
});

it('restores a rejected applicant to active and opens a fresh stage row', function () {
    $this->actingAs($this->admin)
        ->patch("/livehost/recruitment/applicants/{$this->applicant->id}/restore")
        ->assertRedirect();

    $applicant = $this->applicant->fresh();
    expect($applicant->status)->toBe('active');

    $openRows = LiveHostApplicantStage::where('applicant_id', $this->applicant->id)
        ->whereNull('exited_at')
        ->get();

    expect($openRows)->toHaveCount(1)
        ->and($openRows->first()->stage_id)->toBe($this->stage->id);
});

it('writes a "restored" history entry', function () {
    $this->actingAs($this->admin)
        ->patch("/livehost/recruitment/applicants/{$this->applicant->id}/restore")
        ->assertRedirect();

    $latest = $this->applicant->history()->latest('id')->first();
    expect($latest->action)->toBe('restored')
        ->and($latest->to_stage_id)->toBe($this->stage->id)
        ->and($latest->changed_by)->toBe($this->admin->id);
});

it('rejects restoring an applicant that is already active', function () {
    $this->applicant->update(['status' => 'active']);

    $this->actingAs($this->admin)
        ->patch("/livehost/recruitment/applicants/{$this->applicant->id}/restore")
        ->assertStatus(422);
});

it('rejects restoring a hired applicant', function () {
    $this->applicant->update(['status' => 'hired']);

    $this->actingAs($this->admin)
        ->patch("/livehost/recruitment/applicants/{$this->applicant->id}/restore")
        ->assertStatus(422);
});

it('forbids livehost assistants', function () {
    $assistant = User::factory()->create(['role' => 'livehost_assistant']);

    $this->actingAs($assistant)
        ->patch("/livehost/recruitment/applicants/{$this->applicant->id}/restore")
        ->assertForbidden();
});

it('closes any pre-existing open row before opening a fresh one', function () {
    app(ApplicantStageTransition::class)->enterFirstStage($this->applicant);

    $this->actingAs($this->admin)
        ->patch("/livehost/recruitment/applicants/{$this->applicant->id}/restore")
        ->assertRedirect();

    expect(
        LiveHostApplicantStage::where('applicant_id', $this->applicant->id)
            ->whereNull('exited_at')->count()
    )->toBe(1);
});
