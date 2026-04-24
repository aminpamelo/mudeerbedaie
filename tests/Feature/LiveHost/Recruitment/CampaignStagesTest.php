<?php

use App\Models\LiveHostRecruitmentCampaign;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('seeds 4 default stages when a campaign is created', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    expect($campaign->stages()->count())->toBe(4);

    $names = $campaign->stages()->orderBy('position')->pluck('name')->all();
    expect($names)->toEqual(['Review', 'Interview', 'Test Live', 'Final']);

    expect($campaign->stages()->where('is_final', true)->count())->toBe(1);
    expect($campaign->stages()->where('is_final', true)->first()->name)->toBe('Final');
});
