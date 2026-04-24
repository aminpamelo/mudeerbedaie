<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function adminLivehost(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

it('lists campaigns on the index page', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['title' => 'April TikTok Hiring']);

    $response = $this->actingAs($admin)->get(route('livehost.recruitment.campaigns.index'));

    $response->assertOk();
    $response->assertSee('April TikTok Hiring');
});

it('creates a new campaign as a draft', function () {
    $admin = adminLivehost();

    $response = $this->actingAs($admin)->post(route('livehost.recruitment.campaigns.store'), [
        'title' => 'April 2026 Live Host Hiring',
        'slug' => 'april-2026-live-host-hiring',
        'description' => 'Hiring TikTok live hosts.',
        'target_count' => 5,
    ]);

    $campaign = LiveHostRecruitmentCampaign::where('slug', 'april-2026-live-host-hiring')->firstOrFail();

    $response->assertRedirect(route('livehost.recruitment.campaigns.edit', $campaign));
    expect($campaign->status)->toBe('draft');
    expect($campaign->created_by)->toBe($admin->id);
    expect($campaign->stages()->count())->toBe(4); // default stages seeded on creation
});

it('rejects duplicate slugs on create', function () {
    $admin = adminLivehost();
    LiveHostRecruitmentCampaign::factory()->create(['slug' => 'taken-slug']);

    $response = $this->actingAs($admin)
        ->from(route('livehost.recruitment.campaigns.create'))
        ->post(route('livehost.recruitment.campaigns.store'), [
            'title' => 'Another Campaign',
            'slug' => 'taken-slug',
        ]);

    $response->assertSessionHasErrors('slug');
});

it('updates campaign details without touching status', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'open']);

    $this->actingAs($admin)->put(route('livehost.recruitment.campaigns.update', $campaign), [
        'title' => 'Updated title',
        'slug' => $campaign->slug,
        'description' => 'Updated description.',
        'status' => 'draft', // should be ignored
        'target_count' => 12,
    ])->assertRedirect();

    $campaign->refresh();
    expect($campaign->title)->toBe('Updated title');
    expect($campaign->description)->toBe('Updated description.');
    expect($campaign->target_count)->toBe(12);
    expect($campaign->status)->toBe('open'); // unchanged
});

it('publishes a draft campaign when a final stage exists', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'draft']);

    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.publish', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe('open');
});

it('blocks publishing when no stage is marked final', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'draft']);
    $campaign->stages()->update(['is_final' => false]);

    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.publish', $campaign))
        ->assertStatus(422);

    expect($campaign->fresh()->status)->toBe('draft');
});

it('blocks publishing from non-draft statuses', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'open']);

    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.publish', $campaign))
        ->assertStatus(422);
});

it('pauses an open campaign', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'open']);

    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.pause', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe('paused');
});

it('refuses to pause a non-open campaign', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'draft']);

    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.pause', $campaign))
        ->assertStatus(422);
});

it('closes an open or paused campaign and refuses to re-open it', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'paused']);

    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.close', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe('closed');

    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.publish', $campaign))
        ->assertStatus(422);
    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.pause', $campaign))
        ->assertStatus(422);
    $this->actingAs($admin)
        ->patch(route('livehost.recruitment.campaigns.close', $campaign))
        ->assertStatus(422);
});

it('deletes a campaign with no applicants', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $this->actingAs($admin)
        ->delete(route('livehost.recruitment.campaigns.destroy', $campaign))
        ->assertRedirect(route('livehost.recruitment.campaigns.index'));

    expect(LiveHostRecruitmentCampaign::find($campaign->id))->toBeNull();
});

it('blocks deleting a campaign that already has applicants', function () {
    $admin = adminLivehost();
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    LiveHostApplicant::factory()->create(['campaign_id' => $campaign->id]);

    $this->actingAs($admin)
        ->delete(route('livehost.recruitment.campaigns.destroy', $campaign))
        ->assertStatus(422);

    expect(LiveHostRecruitmentCampaign::find($campaign->id))->not->toBeNull();
});

it('rejects non-admin users', function () {
    $user = User::factory()->create(['role' => 'student']);
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $this->actingAs($user)
        ->get(route('livehost.recruitment.campaigns.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('livehost.recruitment.campaigns.store'), [
            'title' => 'x',
            'slug' => 'x',
        ])->assertForbidden();
});
