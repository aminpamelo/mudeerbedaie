<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Support\Recruitment\DefaultFormSchema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('mirrors form_data email-role value into the email column', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $applicant = LiveHostApplicant::create([
        'campaign_id' => $campaign->id,
        'applicant_number' => LiveHostApplicant::generateApplicantNumber(),
        'email' => 'ignored@initial.example',
        'form_data' => [
            'f_email' => 'real@example.com',
            'f_name' => 'Ahmad',
        ],
        'form_schema_snapshot' => DefaultFormSchema::get(),
        'status' => 'active',
        'applied_at' => now(),
    ]);

    expect($applicant->fresh()->email)->toBe('real@example.com');
});

it('does not overwrite email when no role=email field exists in snapshot', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $snapshotWithoutEmailRole = DefaultFormSchema::get();
    // strip role=email
    foreach ($snapshotWithoutEmailRole['pages'] as &$page) {
        foreach ($page['fields'] as &$field) {
            if (($field['role'] ?? null) === 'email') {
                unset($field['role']);
            }
        }
    }
    unset($page, $field);

    $applicant = LiveHostApplicant::create([
        'campaign_id' => $campaign->id,
        'applicant_number' => LiveHostApplicant::generateApplicantNumber(),
        'email' => 'keep@example.com',
        'form_data' => ['f_email' => 'different@example.com'],
        'form_schema_snapshot' => $snapshotWithoutEmailRole,
        'status' => 'active',
        'applied_at' => now(),
    ]);

    expect($applicant->fresh()->email)->toBe('keep@example.com');
});
