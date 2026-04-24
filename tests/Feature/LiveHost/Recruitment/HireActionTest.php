<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStageHistory;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function hireAdmin(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

/*
 |--------------------------------------------------------------------------
 | hire()
 |--------------------------------------------------------------------------
 */

it('hires an active applicant at the final stage and creates a live_host user', function () {
    $admin = hireAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $finalStage = $campaign->stages()->where('is_final', true)->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $finalStage->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('livehost.recruitment.applicants.hire', $applicant), [
            'full_name' => 'Ahmad Rahman',
            'email' => 'ahmad@livehost.test',
            'phone' => '60187654321',
        ]);

    $response->assertRedirect();

    $hired = User::where('email', 'ahmad@livehost.test')->first();
    expect($hired)->not->toBeNull();
    expect($hired->role)->toBe('live_host');
    expect($hired->name)->toBe('Ahmad Rahman');
    expect($hired->phone)->toBe('60187654321');
    expect($hired->email_verified_at)->not->toBeNull();

    $applicant->refresh();
    expect($applicant->status)->toBe('hired');
    expect($applicant->hired_user_id)->toBe($hired->id);
    expect($applicant->hired_at)->not->toBeNull();

    $history = LiveHostApplicantStageHistory::where('applicant_id', $applicant->id)
        ->where('action', 'hired')
        ->first();
    expect($history)->not->toBeNull();
    expect($history->from_stage_id)->toBe($finalStage->id);
    expect($history->to_stage_id)->toBeNull();
    expect($history->changed_by)->toBe($admin->id);
});

it('blocks hiring when the applicant is not on the final stage', function () {
    $admin = hireAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $firstStage = $campaign->stages()->orderBy('position')->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('livehost.recruitment.applicants.hire', $applicant), [
            'full_name' => 'Should Not Hire',
            'email' => 'nope@livehost.test',
            'phone' => '60187654321',
        ])
        ->assertStatus(422);

    expect(User::where('email', 'nope@livehost.test')->exists())->toBeFalse();
    expect($applicant->fresh()->status)->toBe('active');
});

it('blocks hiring when the applicant is not active', function () {
    $admin = hireAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $finalStage = $campaign->stages()->where('is_final', true)->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $finalStage->id,
        'status' => 'rejected',
    ]);

    $this->actingAs($admin)
        ->post(route('livehost.recruitment.applicants.hire', $applicant), [
            'full_name' => 'Should Not Hire',
            'email' => 'nope2@livehost.test',
            'phone' => '60187654321',
        ])
        ->assertStatus(422);

    expect(User::where('email', 'nope2@livehost.test')->exists())->toBeFalse();
});

it('validates that the hire email is unique across users', function () {
    $admin = hireAdmin();
    User::factory()->create(['email' => 'taken@livehost.test']);

    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $finalStage = $campaign->stages()->where('is_final', true)->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $finalStage->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('livehost.recruitment.applicants.hire', $applicant), [
            'full_name' => 'Duplicate Email',
            'email' => 'taken@livehost.test',
            'phone' => '60187654321',
        ])
        ->assertSessionHasErrors('email');

    expect($applicant->fresh()->status)->toBe('active');
});

/*
 |--------------------------------------------------------------------------
 | passwordResetLink()
 |--------------------------------------------------------------------------
 */

it('returns a password reset URL after an applicant is hired', function () {
    $admin = hireAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $finalStage = $campaign->stages()->where('is_final', true)->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $finalStage->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('livehost.recruitment.applicants.hire', $applicant), [
            'full_name' => 'Reset Candidate',
            'email' => 'reset@livehost.test',
            'phone' => '60187654321',
        ])
        ->assertRedirect();

    $response = $this->actingAs($admin)
        ->postJson(route('livehost.recruitment.applicants.password-reset-link', $applicant->fresh()));

    $response->assertOk()
        ->assertJsonStructure(['url']);

    $url = $response->json('url');
    expect($url)->toBeString();
    expect($url)->toContain('reset-password/');
    expect($url)->toContain('email=reset%40livehost.test');
});

it('returns 404 when requesting a password reset link before hire', function () {
    $admin = hireAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $firstStage = $campaign->stages()->orderBy('position')->first();

    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $firstStage->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->postJson(route('livehost.recruitment.applicants.password-reset-link', $applicant))
        ->assertNotFound();
});
