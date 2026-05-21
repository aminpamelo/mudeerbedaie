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

it('stores due_at sent from the JS datetime-local input as a MySQL-compatible datetime in app timezone', function () {
    // The React modal calls Date.toISOString() which produces a UTC string with `T` and `.000Z`.
    // The Eloquent `datetime` cast is bypassed by query-builder bulk update in the controller,
    // so the raw value MUST already be MySQL-compatible (Y-m-d H:i:s) at write time.
    // 04:25 UTC = 12:25 in Asia/Kuala_Lumpur.
    $jsIsoFromFrontend = '2026-05-23T04:25:00.000Z';

    $this->actingAs($this->admin)
        ->patch(applicantUrl($this->applicant), [
            'due_at' => $jsIsoFromFrontend,
            'stage_notes' => 'hantar skrip',
        ])
        ->assertNoContent();

    $row = LiveHostApplicantStage::where('applicant_id', $this->applicant->id)
        ->whereNull('exited_at')->first();

    // Raw stored value (bypass cast) must be MySQL-safe: no `T`, no `Z`, no millis.
    $rawDueAt = $row->getRawOriginal('due_at');
    expect($rawDueAt)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');

    // Convention in this app: datetimes are stored in Asia/Kuala_Lumpur local time.
    expect($rawDueAt)->toBe('2026-05-23 12:25:00');

    // The stage notes should also persist (the user-reported symptom).
    expect($row->stage_notes)->toBe('hantar skrip');
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

it('allows livehost assistants to edit current stage details', function () {
    $assistant = User::factory()->create(['role' => 'livehost_assistant']);

    $this->actingAs($assistant)
        ->patch(applicantUrl($this->applicant), ['stage_notes' => 'set by assistant'])
        ->assertNoContent();
});
