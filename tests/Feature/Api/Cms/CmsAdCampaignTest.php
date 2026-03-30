<?php

use App\Models\AdCampaign;
use App\Models\AdStat;
use App\Models\Content;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $this->actingAs($this->user, 'sanctum');
});

// === List Ad Campaigns ===

it('can list ad campaigns', function () {
    AdCampaign::factory()->count(2)->create(['assigned_by' => $this->employee->id]);

    $response = $this->getJson('/api/cms/ads');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
});

// === Create Ad Campaign ===

it('can create ad campaign', function () {
    $content = Content::factory()->posted()->markedForAds()->create([
        'created_by' => $this->employee->id,
    ]);

    $response = $this->postJson('/api/cms/ads', [
        'content_id' => $content->id,
        'platform' => 'tiktok',
        'budget' => 500.00,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
    ]);

    $response->assertCreated();
    expect(AdCampaign::where('content_id', $content->id)->exists())->toBeTrue();
});

// === Show Ad Campaign ===

it('can show ad campaign', function () {
    $campaign = AdCampaign::factory()->create(['assigned_by' => $this->employee->id]);

    $response = $this->getJson("/api/cms/ads/{$campaign->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $campaign->id);
});

// === Update Ad Campaign ===

it('can update ad campaign', function () {
    $campaign = AdCampaign::factory()->create(['assigned_by' => $this->employee->id]);

    $response = $this->putJson("/api/cms/ads/{$campaign->id}", [
        'status' => 'running',
    ]);

    $response->assertSuccessful();
    expect($campaign->fresh()->status)->toBe('running');
});

// === Add Stats to Campaign ===

it('can add stats to campaign', function () {
    $campaign = AdCampaign::factory()->create(['assigned_by' => $this->employee->id]);

    $response = $this->postJson("/api/cms/ads/{$campaign->id}/stats", [
        'impressions' => 10000,
        'clicks' => 500,
        'spend' => 150.50,
        'conversions' => 25,
    ]);

    $response->assertCreated();
    expect(AdStat::where('ad_campaign_id', $campaign->id)->count())->toBe(1);
});
