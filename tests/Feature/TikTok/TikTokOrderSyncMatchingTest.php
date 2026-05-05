<?php

declare(strict_types=1);

use App\Actions\LiveHost\MatchProductOrderToLiveSession;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
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
