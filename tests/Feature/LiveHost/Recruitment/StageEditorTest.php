<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function stageAdmin(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

it('adds a stage to a campaign at the next position', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $originalCount = $campaign->stages()->count();

    $this->actingAs($admin)
        ->post(route('livehost.recruitment.campaigns.stages.store', $campaign), [
            'name' => 'Background Check',
        ])->assertRedirect();

    expect($campaign->stages()->count())->toBe($originalCount + 1);
    $newStage = LiveHostRecruitmentStage::where('campaign_id', $campaign->id)
        ->orderByDesc('position')
        ->first();
    expect($newStage->name)->toBe('Background Check');
    expect($newStage->position)->toBe($originalCount + 1);
});

it('renames a stage', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $stage = $campaign->stages()->first();

    $this->actingAs($admin)
        ->put(route('livehost.recruitment.campaigns.stages.update', [$campaign, $stage]), [
            'name' => 'Phone Screen',
            'description' => 'Initial 15-min call.',
        ])->assertRedirect();

    $stage->refresh();
    expect($stage->name)->toBe('Phone Screen');
    expect($stage->description)->toBe('Initial 15-min call.');
});

it('enforces exactly one final stage when setting is_final=true', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $originalFinal = $campaign->stages()->where('is_final', true)->first();
    expect($originalFinal)->not->toBeNull();

    $notFinal = $campaign->stages()->where('is_final', false)->first();

    $this->actingAs($admin)
        ->put(route('livehost.recruitment.campaigns.stages.update', [$campaign, $notFinal]), [
            'name' => $notFinal->name,
            'is_final' => true,
        ])->assertRedirect();

    expect($campaign->stages()->where('is_final', true)->count())->toBe(1);
    expect($campaign->stages()->where('is_final', true)->first()->id)->toBe($notFinal->id);
    expect($originalFinal->fresh()->is_final)->toBeFalse();
});

it('enforces exactly one final stage when creating a stage with is_final=true', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $this->actingAs($admin)
        ->post(route('livehost.recruitment.campaigns.stages.store', $campaign), [
            'name' => 'Brand New Final',
            'is_final' => true,
        ])->assertRedirect();

    expect($campaign->stages()->where('is_final', true)->count())->toBe(1);
    expect($campaign->stages()->where('is_final', true)->first()->name)->toBe('Brand New Final');
});

it('reorders stages by stage_ids array', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $ids = $campaign->stages()->orderBy('position')->pluck('id')->all();
    $reversed = array_reverse($ids);

    $this->actingAs($admin)
        ->put(route('livehost.recruitment.campaigns.stages.reorder', $campaign), [
            'stage_ids' => $reversed,
        ])->assertRedirect();

    $afterIds = $campaign->stages()->orderBy('position')->pluck('id')->all();
    expect($afterIds)->toEqual($reversed);

    // Positions should be 1..N
    $positions = $campaign->stages()->orderBy('position')->pluck('position')->all();
    expect($positions)->toEqual(range(1, count($afterIds)));
});

it('rejects reorder payloads that do not cover all campaign stages', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $ids = $campaign->stages()->orderBy('position')->pluck('id')->all();
    $partial = array_slice($ids, 0, 2);

    $this->actingAs($admin)
        ->put(route('livehost.recruitment.campaigns.stages.reorder', $campaign), [
            'stage_ids' => $partial,
        ])->assertStatus(422);
});

it('deletes a stage that has no applicants and is not the only final stage', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $stage = $campaign->stages()->where('is_final', false)->first();

    $this->actingAs($admin)
        ->delete(route('livehost.recruitment.campaigns.stages.destroy', [$campaign, $stage]))
        ->assertRedirect();

    expect(LiveHostRecruitmentStage::find($stage->id))->toBeNull();
});

it('blocks deleting a stage that currently has applicants', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $stage = $campaign->stages()->first();
    LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $stage->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('livehost.recruitment.campaigns.stages.destroy', [$campaign, $stage]))
        ->assertStatus(422);

    expect(LiveHostRecruitmentStage::find($stage->id))->not->toBeNull();
});

it('blocks deleting the only final stage when other stages exist', function () {
    $admin = stageAdmin();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $finalStage = $campaign->stages()->where('is_final', true)->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('livehost.recruitment.campaigns.stages.destroy', [$campaign, $finalStage]))
        ->assertStatus(422);

    expect(LiveHostRecruitmentStage::find($finalStage->id))->not->toBeNull();
});

it('refuses stage access from a different campaign', function () {
    $admin = stageAdmin();
    $campaign1 = LiveHostRecruitmentCampaign::factory()->create();
    $campaign2 = LiveHostRecruitmentCampaign::factory()->create();
    $foreignStage = $campaign2->stages()->first();

    $this->actingAs($admin)
        ->put(route('livehost.recruitment.campaigns.stages.update', [$campaign1, $foreignStage]), [
            'name' => 'Hijack',
        ])->assertNotFound();

    $this->actingAs($admin)
        ->delete(route('livehost.recruitment.campaigns.stages.destroy', [$campaign1, $foreignStage]))
        ->assertNotFound();
});
