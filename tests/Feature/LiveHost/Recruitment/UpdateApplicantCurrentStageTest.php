<?php

declare(strict_types=1);

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use App\Models\User;

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
    ]);
    app(\App\Services\Recruitment\ApplicantStageTransition::class)->enterFirstStage($this->applicant);
});

function applicantUrl(LiveHostApplicant $a): string
{
    return "/livehost/recruitment/applicants/{$a->id}/current-stage";
}

it('updates assignee, due_at, and stage_notes on the open row', function () {
    $assignee = User::factory()->create(['role' => 'admin']);
    $due = now()->addDays(3)->startOfMinute();

    $this->actingAs($this->admin)
        ->patch(applicantUrl($this->applicant), [
            'assignee_id' => $assignee->id,
            'due_at' => $due->toIso8601String(),
            'stage_notes' => 'Schedule with candidate.',
        ])
        ->assertNoContent();

    $row = LiveHostApplicantStage::where('applicant_id', $this->applicant->id)
        ->whereNull('exited_at')->first();

    expect($row->assignee_id)->toBe($assignee->id)
        ->and($row->due_at?->toIso8601String())->toBe($due->toIso8601String())
        ->and($row->stage_notes)->toBe('Schedule with candidate.');
});

it('rejects an assignee whose role is not admin or admin_livehost', function () {
    $hostUser = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($this->admin)
        ->patch(applicantUrl($this->applicant), ['assignee_id' => $hostUser->id])
        ->assertSessionHasErrors('assignee_id');
});

it('returns 409 when there is no open stage row', function () {
    LiveHostApplicantStage::where('applicant_id', $this->applicant->id)
        ->update(['exited_at' => now()]);

    $this->actingAs($this->admin)
        ->patch(applicantUrl($this->applicant), ['stage_notes' => 'late note'])
        ->assertStatus(409);
});

it('forbids livehost assistants', function () {
    $assistant = User::factory()->create(['role' => 'livehost_assistant']);

    $this->actingAs($assistant)
        ->patch(applicantUrl($this->applicant), ['stage_notes' => 'nope'])
        ->assertForbidden();
});
