<?php

declare(strict_types=1);

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->assistant = User::factory()->create(['role' => 'livehost_assistant']);
});

it('lets a livehost_assistant browse the campaigns index', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['title' => 'May Recruitment']);

    $this->actingAs($this->assistant)
        ->get(route('livehost.recruitment.campaigns.index'))
        ->assertOk()
        ->assertSee('May Recruitment');
});

it('lets a livehost_assistant create a campaign', function () {
    $this->actingAs($this->assistant)
        ->post(route('livehost.recruitment.campaigns.store'), [
            'title' => 'Assistant-created campaign',
            'slug' => 'assistant-created-campaign',
            'description' => 'Spun up by an assistant.',
        ])
        ->assertRedirect();

    expect(LiveHostRecruitmentCampaign::where('slug', 'assistant-created-campaign')->exists())->toBeTrue();
});

it('lets a livehost_assistant view the applicants kanban', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->assistant)
        ->get(route('livehost.recruitment.applicants.index', ['campaign' => $campaign->id]))
        ->assertOk();
});

it('lets a livehost_assistant move an applicant between stages', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $stages = $campaign->stages()->orderBy('position')->get();
    $firstStage = $stages->first();
    $secondStage = $stages->skip(1)->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->assistant)
        ->patch(route('livehost.recruitment.applicants.stage', $applicant), [
            'to_stage_id' => $secondStage->id,
        ])
        ->assertRedirect();

    expect($applicant->fresh()->current_stage_id)->toBe($secondStage->id);
});

it('forbids a livehost_assistant from hiring an applicant', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $finalStage = $campaign->stages()->where('is_final', true)->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $finalStage->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->assistant)
        ->post(route('livehost.recruitment.applicants.hire', $applicant), [
            'full_name' => 'Sarah Hopeful',
            'email' => 'sarah.hopeful@example.com',
            'phone' => '60123456789',
        ])
        ->assertForbidden();

    expect($applicant->fresh()->status)->toBe('active');
    expect(User::where('email', 'sarah.hopeful@example.com')->exists())->toBeFalse();
});

it('forbids a livehost_assistant from generating a password reset link', function () {
    $hiredUser = User::factory()->create(['role' => 'live_host']);
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $finalStage = $campaign->stages()->where('is_final', true)->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $finalStage->id,
        'status' => 'hired',
        'hired_user_id' => $hiredUser->id,
    ]);

    $this->actingAs($this->assistant)
        ->post(route('livehost.recruitment.applicants.password-reset-link', $applicant))
        ->assertForbidden();
});
