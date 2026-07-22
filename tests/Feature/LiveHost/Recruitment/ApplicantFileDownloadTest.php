<?php

declare(strict_types=1);

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

function applicantWithFile(string $path = 'recruitment/resumes/abc123.png'): LiveHostApplicant
{
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    return LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'form_schema_snapshot' => [
            'pages' => [[
                'id' => 'p1',
                'title' => 'Docs',
                'fields' => [
                    ['id' => 'resume', 'type' => 'file', 'label' => 'Resume'],
                ],
            ]],
        ],
        'form_data' => ['resume' => $path],
    ]);
}

it('streams a stored file to an authorised PIC', function () {
    Storage::fake('local');
    Storage::disk('local')->put('recruitment/resumes/abc123.png', 'binary-bytes');

    $applicant = applicantWithFile();

    $this->actingAs($this->pic)
        ->get(route('livehost.recruitment.applicants.file', ['applicant' => $applicant->id, 'field' => 'resume']))
        ->assertOk();
});

it('404s when the stored file is missing', function () {
    Storage::fake('local');

    $applicant = applicantWithFile();

    $this->actingAs($this->pic)
        ->get(route('livehost.recruitment.applicants.file', ['applicant' => $applicant->id, 'field' => 'resume']))
        ->assertNotFound();
});

it('404s when the field is not a file field', function () {
    Storage::fake('local');

    $applicant = applicantWithFile();

    $this->actingAs($this->pic)
        ->get(route('livehost.recruitment.applicants.file', ['applicant' => $applicant->id, 'field' => 'not_a_field']))
        ->assertNotFound();
});

it('blocks guests from downloading applicant files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('recruitment/resumes/abc123.png', 'binary-bytes');

    $applicant = applicantWithFile();

    $this->get(route('livehost.recruitment.applicants.file', ['applicant' => $applicant->id, 'field' => 'resume']))
        ->assertRedirect(route('login'));
});
