<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\User;
use App\Support\Recruitment\DefaultFormSchema;

use function Pest\Laravel\actingAs;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('persists a custom form_schema through the campaign update endpoint', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    $schema = DefaultFormSchema::get();
    // Insert a new "TikTok handle" text field on page 1 (after email).
    $schema['pages'][0]['fields'][] = [
        'id' => 'f_tt_handle',
        'type' => 'text',
        'label' => 'TikTok handle',
        'required' => false,
    ];

    actingAs($admin)
        ->put(route('livehost.recruitment.campaigns.update', $campaign), [
            'title' => $campaign->title,
            'slug' => $campaign->slug,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'form_schema' => $schema,
        ])
        ->assertRedirect();

    $fresh = $campaign->fresh();
    $fields = collect($fresh->form_schema['pages'][0]['fields']);
    expect($fields->firstWhere('id', 'f_tt_handle'))->not->toBeNull();
    expect($fields->firstWhere('id', 'f_tt_handle')['label'])->toBe('TikTok handle');
});

it('renders custom fields on the public form', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $schema = DefaultFormSchema::get();
    $schema['pages'][0]['fields'][] = [
        'id' => 'f_tt_handle',
        'type' => 'text',
        'label' => 'TikTok handle',
        'required' => false,
    ];
    $campaign->update(['form_schema' => $schema]);

    $this->get(route('recruitment.show', $campaign->slug))
        ->assertOk()
        ->assertSee('TikTok handle')
        ->assertSee('name="f_tt_handle"', escape: false);
});

it('keeps the historical snapshot even when the campaign schema changes later', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    $schemaV1 = DefaultFormSchema::get();
    $schemaV1['pages'][0]['fields'][] = [
        'id' => 'f_tt_handle',
        'type' => 'text',
        'label' => 'TikTok handle',
        'required' => false,
    ];
    $campaign->update(['form_schema' => $schemaV1]);

    // Candidate A applies under schema v1.
    $this->post(route('recruitment.apply', $campaign->slug), [
        'f_name' => 'A',
        'f_email' => 'a@x.test',
        'f_phone' => '60100000001',
        'f_platforms' => ['tiktok'],
        'f_tt_handle' => '@a_handle',
    ])->assertRedirect();

    // Admin edits schema: rename the field.
    $schemaV2 = $schemaV1;
    foreach ($schemaV2['pages'][0]['fields'] as &$field) {
        if ($field['id'] === 'f_tt_handle') {
            $field['label'] = 'TikTok @handle (v2)';
        }
    }
    unset($field);
    $campaign->update(['form_schema' => $schemaV2]);

    // Candidate A's snapshot still has the v1 label.
    $a = LiveHostApplicant::where('email', 'a@x.test')->firstOrFail();
    $snapFields = collect($a->form_schema_snapshot['pages'][0]['fields']);
    expect($snapFields->firstWhere('id', 'f_tt_handle')['label'])->toBe('TikTok handle');

    // And form_data stored the submitted value.
    expect($a->form_data['f_tt_handle'])->toBe('@a_handle');
});
