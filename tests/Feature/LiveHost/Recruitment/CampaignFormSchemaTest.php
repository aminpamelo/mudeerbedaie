<?php

use App\Models\LiveHostRecruitmentCampaign;
use App\Models\User;
use App\Support\Recruitment\DefaultFormSchema;

use function Pest\Laravel\actingAs;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin_livehost']);
});

it('saves a valid form_schema on update', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $schema = DefaultFormSchema::get();
    $schema['pages'][0]['fields'][0]['label'] = 'Full legal name';

    actingAs($this->admin)
        ->put(route('livehost.recruitment.campaigns.update', $campaign), [
            'title' => $campaign->title,
            'slug' => $campaign->slug,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'form_schema' => $schema,
        ])
        ->assertRedirect();

    expect($campaign->fresh()->form_schema['pages'][0]['fields'][0]['label'])
        ->toBe('Full legal name');
});

it('returns validation errors for an invalid form_schema on update', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $schema = DefaultFormSchema::get();

    // Strip all email roles to make the schema invalid.
    foreach ($schema['pages'] as &$page) {
        foreach ($page['fields'] as &$field) {
            if (($field['role'] ?? null) === 'email') {
                unset($field['role']);
            }
        }
    }
    unset($page, $field);

    actingAs($this->admin)
        ->from(route('livehost.recruitment.campaigns.edit', $campaign))
        ->put(route('livehost.recruitment.campaigns.update', $campaign), [
            'title' => $campaign->title,
            'slug' => $campaign->slug,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'form_schema' => $schema,
        ])
        ->assertSessionHasErrors(['form_schema']);
});

it('blocks publishing when form_schema is invalid', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'draft']);

    $invalidSchema = DefaultFormSchema::get();
    foreach ($invalidSchema['pages'] as &$page) {
        foreach ($page['fields'] as &$field) {
            if (($field['role'] ?? null) === 'email') {
                unset($field['role']);
            }
        }
    }
    unset($page, $field);

    // Bypass the model cast/observer validation by raw update.
    $campaign->forceFill(['form_schema' => $invalidSchema])->save();

    actingAs($this->admin)
        ->from(route('livehost.recruitment.campaigns.edit', $campaign))
        ->patch(route('livehost.recruitment.campaigns.publish', $campaign))
        ->assertSessionHasErrors(['form_schema']);

    expect($campaign->fresh()->status)->toBe('draft');
});
