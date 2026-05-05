<?php

declare(strict_types=1);

use App\Actions\LiveHost\MatchProductOrderToLiveSession;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Services\TikTok\TikTokOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('matches a tiktok_shop order to its live session via the matcher action', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    // Simulate what the sync service does: create the order, then call the
    // matcher (which is what the hook does internally).
    $order = ProductOrder::create([
        'order_number' => 'PO-TEST-001',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'status' => 'paid',
        'subtotal' => 100,
        'total_amount' => 100,
    ]);

    app(MatchProductOrderToLiveSession::class)->handle($order);

    expect($order->fresh()->matched_live_session_id)->toBe($session->id);
});

it('invokes the matcher when syncing a single order through the sync service', function () {
    // This test exists to lock in the matcher hook inside
    // TikTokOrderSyncService::syncSingleOrder. If that call is accidentally
    // dropped during a future refactor, this test fails.
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    // Minimal TikTok order payload shape — only the fields mapOrderData()
    // actually reads. paid_time/create_time are unix timestamps and Carbon
    // stores them as UTC; we use UTC anchors so paid_time lands at
    // 2026-05-01 11:00:00 UTC, inside the session window above.
    $orderData = [
        'id' => 'TT-INTEGRATION-001',
        'status' => 'AWAITING_SHIPMENT',
        'create_time' => \Carbon\Carbon::parse('2026-05-01 10:30:00', 'UTC')->getTimestamp(),
        'paid_time' => \Carbon\Carbon::parse('2026-05-01 11:00:00', 'UTC')->getTimestamp(),
        'payment' => [
            'sub_total' => '100.00',
            'total_amount' => '100.00',
        ],
        'recipient_address' => [],
        'line_items' => [],
    ];

    $isNew = app(TikTokOrderSyncService::class)->syncSingleOrder($account, $orderData);

    expect($isNew)->toBeTrue();

    $order = ProductOrder::where('platform_order_id', 'TT-INTEGRATION-001')->firstOrFail();

    expect($order->matched_live_session_id)->toBe($session->id);
});
