<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelAnalytics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function funnelWithDailyStats(): array
{
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();

    $make = function (string $date, int $visitors, int $conversions, float $revenue) use ($funnel): void {
        FunnelAnalytics::factory()->create([
            'funnel_id' => $funnel->id,
            'funnel_step_id' => null,
            'date' => $date,
            'unique_visitors' => $visitors,
            'pageviews' => $visitors * 2,
            'conversions' => $conversions,
            'revenue' => $revenue,
        ]);
    };

    $make(today()->toDateString(), 100, 10, 500);
    $make(today()->subDay()->toDateString(), 50, 5, 250);
    $make(today()->subDays(10)->toDateString(), 999, 99, 9999);

    return [$user, $funnel];
}

it('filters funnel analytics to today only', function () {
    [$user, $funnel] = funnelWithDailyStats();

    $data = $this->actingAs($user)
        ->getJson("/api/v1/funnels/{$funnel->uuid}/analytics?period=today")
        ->assertOk()
        ->json('data');

    expect($data['summary']['total_visitors'])->toBe(100)
        ->and($data['summary']['total_conversions'])->toBe(10)
        ->and((float) $data['summary']['total_revenue'])->toBe(500.0)
        ->and($data['range']['period'])->toBe('today')
        ->and($data['range']['start'])->toBe(today()->toDateString())
        ->and($data['range']['end'])->toBe(today()->toDateString());
});

it('filters funnel analytics to yesterday only', function () {
    [$user, $funnel] = funnelWithDailyStats();

    $data = $this->actingAs($user)
        ->getJson("/api/v1/funnels/{$funnel->uuid}/analytics?period=yesterday")
        ->assertOk()
        ->json('data');

    expect($data['summary']['total_visitors'])->toBe(50)
        ->and($data['summary']['total_conversions'])->toBe(5)
        ->and((float) $data['summary']['total_revenue'])->toBe(250.0);
});

it('filters funnel analytics to a custom date range (inclusive)', function () {
    [$user, $funnel] = funnelWithDailyStats();

    $start = today()->subDay()->toDateString();
    $end = today()->toDateString();

    $data = $this->actingAs($user)
        ->getJson("/api/v1/funnels/{$funnel->uuid}/analytics?period=custom&start_date={$start}&end_date={$end}")
        ->assertOk()
        ->json('data');

    // today (100/10/500) + yesterday (50/5/250) = 150/15/750; the 10-day-old row is excluded.
    expect($data['summary']['total_visitors'])->toBe(150)
        ->and($data['summary']['total_conversions'])->toBe(15)
        ->and((float) $data['summary']['total_revenue'])->toBe(750.0);
});

it('rejects a custom range whose end is before its start', function () {
    [$user, $funnel] = funnelWithDailyStats();

    $this->actingAs($user)
        ->getJson("/api/v1/funnels/{$funnel->uuid}/analytics?period=custom&start_date=2026-07-10&end_date=2026-07-01")
        ->assertStatus(422);
});

it('rejects an unknown period', function () {
    [$user, $funnel] = funnelWithDailyStats();

    $this->actingAs($user)
        ->getJson("/api/v1/funnels/{$funnel->uuid}/analytics?period=all-time")
        ->assertStatus(422);
});
