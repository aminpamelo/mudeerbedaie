<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStageHistory;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function reviewAdmin(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

/*
 |--------------------------------------------------------------------------
 | index()
 |--------------------------------------------------------------------------
 */

it('renders the kanban board with applicants grouped into their stages', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $stages = $campaign->stages()->orderBy('position')->get();

    $applicantA = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $stages->first()->id,
        'status' => 'active',
    ]);
    $applicantB = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $stages->last()->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('livehost.recruitment.applicants.index', ['campaign' => $campaign->id]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recruitment/applicants/Index', false)
                ->where('campaign.id', $campaign->id)
                ->has('stages', 4)
                ->has('applicants', 2)
                ->where('filters.status', 'active')
        );
});

it('filters applicants by the status tab', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    LiveHostApplicant::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => 'active',
    ]);
    LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'rejected',
    ]);
    LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'hired',
    ]);

    $this->actingAs($admin)
        ->get(route('livehost.recruitment.applicants.index', [
            'campaign' => $campaign->id,
            'status' => 'rejected',
        ]))
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recruitment/applicants/Index', false)
                ->where('filters.status', 'rejected')
                ->has('applicants', 1)
        );

    $this->actingAs($admin)
        ->get(route('livehost.recruitment.applicants.index', [
            'campaign' => $campaign->id,
            'status' => 'hired',
        ]))
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('applicants', 1)
                ->where('filters.status', 'hired')
        );
});

it('honors the campaign selector query param', function () {
    $admin = reviewAdmin();
    $alpha = LiveHostRecruitmentCampaign::factory()->open()->create();
    $beta = LiveHostRecruitmentCampaign::factory()->open()->create();

    LiveHostApplicant::factory()->count(3)->create(['campaign_id' => $alpha->id, 'status' => 'active']);
    LiveHostApplicant::factory()->count(1)->create(['campaign_id' => $beta->id, 'status' => 'active']);

    $this->actingAs($admin)
        ->get(route('livehost.recruitment.applicants.index', ['campaign' => $beta->id]))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('campaign.id', $beta->id)
                ->has('applicants', 1)
        );
});

/*
 |--------------------------------------------------------------------------
 | show()
 |--------------------------------------------------------------------------
 */

it('renders the applicant detail page with campaign stages and history', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $firstStage = $campaign->stages()->orderBy('position')->first();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'active',
    ]);

    // Seed one history row so the payload has something to render.
    $applicant->history()->create([
        'to_stage_id' => $firstStage->id,
        'action' => 'applied',
    ]);

    $this->actingAs($admin)
        ->get(route('livehost.recruitment.applicants.show', $applicant))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recruitment/applicants/Show', false)
                ->where('applicant.id', $applicant->id)
                ->where('applicant.applicant_number', $applicant->applicant_number)
                ->where('applicant.current_stage.id', $firstStage->id)
                ->has('stages', 4)
                ->has('history', 1)
                ->where('history.0.action', 'applied')
        );
});

/*
 |--------------------------------------------------------------------------
 | moveStage()
 |--------------------------------------------------------------------------
 */

it('advances an applicant to a later stage and records history', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $stages = $campaign->stages()->orderBy('position')->get();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $stages->first()->id,
        'status' => 'active',
    ]);

    $targetStage = $stages->get(1);

    $this->actingAs($admin)
        ->patch(
            route('livehost.recruitment.applicants.stage', $applicant),
            ['to_stage_id' => $targetStage->id]
        )
        ->assertRedirect();

    expect($applicant->fresh()->current_stage_id)->toBe($targetStage->id);

    $history = LiveHostApplicantStageHistory::where('applicant_id', $applicant->id)
        ->where('action', 'advanced')
        ->first();
    expect($history)->not->toBeNull();
    expect($history->from_stage_id)->toBe($stages->first()->id);
    expect($history->to_stage_id)->toBe($targetStage->id);
    expect($history->changed_by)->toBe($admin->id);
});

it('records a reverted action when moving to an earlier stage', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $stages = $campaign->stages()->orderBy('position')->get();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $stages->get(2)->id,
        'status' => 'active',
    ]);

    $earlier = $stages->first();

    $this->actingAs($admin)
        ->patch(
            route('livehost.recruitment.applicants.stage', $applicant),
            ['to_stage_id' => $earlier->id]
        )
        ->assertRedirect();

    $history = LiveHostApplicantStageHistory::where('applicant_id', $applicant->id)
        ->latest()
        ->first();
    expect($history->action)->toBe('reverted');
    expect($history->from_stage_id)->toBe($stages->get(2)->id);
    expect($history->to_stage_id)->toBe($earlier->id);
});

it('refuses to move a non-active applicant', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $firstStage = $campaign->stages()->orderBy('position')->first();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'rejected',
    ]);

    $this->actingAs($admin)
        ->patch(
            route('livehost.recruitment.applicants.stage', $applicant),
            ['to_stage_id' => $campaign->stages()->orderBy('position')->skip(1)->first()->id]
        )
        ->assertStatus(422);
});

it('rejects stage moves to a stage owned by another campaign', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $foreignCampaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $foreignStage = $foreignCampaign->stages()->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $campaign->stages()->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->patch(
            route('livehost.recruitment.applicants.stage', $applicant),
            ['to_stage_id' => $foreignStage->id]
        )
        ->assertStatus(422);

    expect($applicant->fresh()->current_stage_id)->not->toBe($foreignStage->id);
});

/*
 |--------------------------------------------------------------------------
 | reject()
 |--------------------------------------------------------------------------
 */

it('rejects an active applicant and writes a history row', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $firstStage = $campaign->stages()->orderBy('position')->first();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->patch(
            route('livehost.recruitment.applicants.reject', $applicant),
            ['notes' => 'Not a fit for live selling']
        )
        ->assertRedirect();

    $applicant->refresh();
    expect($applicant->status)->toBe('rejected');

    $history = LiveHostApplicantStageHistory::where('applicant_id', $applicant->id)
        ->where('action', 'rejected')
        ->first();
    expect($history)->not->toBeNull();
    expect($history->from_stage_id)->toBe($firstStage->id);
    expect($history->to_stage_id)->toBeNull();
    expect($history->notes)->toBe('Not a fit for live selling');
    expect($history->changed_by)->toBe($admin->id);

    // Rejected applicants disappear from the active kanban tab.
    $this->actingAs($admin)
        ->get(route('livehost.recruitment.applicants.index', [
            'campaign' => $campaign->id,
            'status' => 'active',
        ]))
        ->assertInertia(fn (Assert $page) => $page->has('applicants', 0));
});

it('refuses to reject an already-rejected applicant', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $campaign->stages()->first()->id,
        'status' => 'rejected',
    ]);

    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.applicants.reject', $applicant))
        ->assertStatus(422);
});

/*
 |--------------------------------------------------------------------------
 | updateNotes()
 |--------------------------------------------------------------------------
 */

it('persists admin notes and returns 204 No Content', function () {
    $admin = reviewAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $campaign->stages()->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->patch(
            route('livehost.recruitment.applicants.notes', $applicant),
            ['notes' => 'Looks promising — schedule a call.']
        )
        ->assertNoContent();

    expect($applicant->fresh()->notes)->toBe('Looks promising — schedule a call.');
});
