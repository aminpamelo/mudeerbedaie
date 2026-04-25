<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-04-25 10:00:00'));

it('streams a CSV with header + one row per host', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Sarah Chen']);
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => '2026-04-10 08:00:00',
        'duration_minutes' => 120,
        'gmv_amount' => 500.00,
    ]);

    $response = actingAs($admin)
        ->get('/livehost/reports/host-scorecard/export?dateFrom=2026-04-01&dateTo=2026-04-25')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    $content = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", $content)));
    expect($lines[0])->toContain('Host')->toContain('GMV');
    expect($lines[1])->toContain('Sarah Chen')->toContain('500');
});
