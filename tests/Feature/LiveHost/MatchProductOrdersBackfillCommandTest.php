<?php

declare(strict_types=1);

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills matched_live_session_id on tiktok_shop orders', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    $matchableOrder = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'matched_live_session_id' => null,
    ]);
    $manualOrder = ProductOrder::factory()->create([
        'source' => 'manual',
        'matched_live_session_id' => null,
    ]);

    $this->artisan('livehost:match-product-orders')->assertExitCode(0);

    expect($matchableOrder->fresh()->matched_live_session_id)->toBe($session->id);
    expect($manualOrder->fresh()->matched_live_session_id)->toBeNull();
});

it('skips orders that are already matched', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'matched_live_session_id' => $session->id,
    ]);

    $this->artisan('livehost:match-product-orders')->assertExitCode(0);

    // No change — same id.
    expect($order->fresh()->matched_live_session_id)->toBe($session->id);
});
