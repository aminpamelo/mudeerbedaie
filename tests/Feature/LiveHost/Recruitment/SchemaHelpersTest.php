<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Support\Recruitment\DefaultFormSchema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('casts form_schema as array on campaign', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    expect($campaign->form_schema)->toBeArray();
    expect($campaign->form_schema['version'])->toBe(1);
});

it('finds a field by role on the campaign', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $email = $campaign->getFieldByRole('email');

    expect($email)->not->toBeNull();
    expect($email['id'])->toBe('f_email');
});

it('resolves applicant attributes via snapshot + form_data roles', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'email' => 'ahmad@example.com',
        'form_data' => [
            'f_name' => 'Ahmad Rahman',
            'f_email' => 'ahmad@example.com',
            'f_phone' => '60123456789',
        ],
        'form_schema_snapshot' => DefaultFormSchema::get(),
    ]);

    expect($applicant->name)->toBe('Ahmad Rahman');
    expect($applicant->phone)->toBe('60123456789');
    expect($applicant->valueByRole('email'))->toBe('ahmad@example.com');
});
