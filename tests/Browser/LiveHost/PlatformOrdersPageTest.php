<?php

declare(strict_types=1);

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\User;

it('renders the platform orders page with data', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost', 'name' => 'Test Admin']);
    $account = PlatformAccount::factory()->create(['name' => 'Test Shop A']);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);
    ProductOrder::factory()->create([
        'order_number' => 'PO-BROWSER-1',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'matched_live_session_id' => $session->id,
        'status' => 'paid',
        'total_amount' => 99.50,
        'currency' => 'MYR',
    ]);

    $this->actingAs($admin);

    visit('/livehost/orders')
        ->assertSee('Platform Orders')
        ->assertSee('PO-BROWSER-1')
        ->assertNoJavascriptErrors();
});

it('filters by unmatched_only via the URL', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    ProductOrder::factory()->create([
        'order_number' => 'PO-MATCHED',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'matched_live_session_id' => $session->id,
        'status' => 'paid',
    ]);
    ProductOrder::factory()->create([
        'order_number' => 'PO-UNMATCHED',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-15 11:00:00',
        'matched_live_session_id' => null,
        'status' => 'paid',
    ]);

    $this->actingAs($admin);

    visit('/livehost/orders?unmatched_only=1')
        ->assertSee('PO-UNMATCHED')
        ->assertDontSee('PO-MATCHED')
        ->assertNoJavascriptErrors();
});
