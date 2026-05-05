<?php

declare(strict_types=1);

use App\Actions\LiveHost\MatchProductOrderToLiveSession;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(MatchProductOrderToLiveSession::class);
});

it('matches a tiktok_shop order whose paid_time falls inside session window', function () {
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
        'matched_live_session_id' => null,
    ]);

    $matched = $this->action->handle($order);

    expect($matched)->toBe($session->id);
    expect($order->fresh()->matched_live_session_id)->toBe($session->id);
});

it('returns null when source is not tiktok_shop', function () {
    $order = ProductOrder::factory()->create(['source' => 'manual', 'paid_time' => now()]);
    expect($this->action->handle($order))->toBeNull();
});

it('returns null when platform_account_id is missing', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => null,
        'paid_time' => now(),
    ]);
    expect($this->action->handle($order))->toBeNull();
});

it('does not match a session from a different platform account', function () {
    $sessionAccount = PlatformAccount::factory()->create();
    $orderAccount = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'platform_account_id' => $sessionAccount->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $orderAccount->id,
        'paid_time' => '2026-05-01 11:00:00',
    ]);

    expect($this->action->handle($order))->toBeNull();
});

it('does not match an order paid before the session started', function () {
    $account = PlatformAccount::factory()->create();
    LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 09:00:00',
    ]);

    expect($this->action->handle($order))->toBeNull();
});

it('matches within the 12h tail window after actual_end_at', function () {
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
        'paid_time' => '2026-05-01 23:30:00', // 11.5h after end
    ]);

    expect($this->action->handle($order))->toBe($session->id);
});

it('does not match orders past the 12h tail window', function () {
    $account = PlatformAccount::factory()->create();
    LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-02 01:00:00', // 13h after end
    ]);

    expect($this->action->handle($order))->toBeNull();
});

it('falls back to created_at when paid_time is null', function () {
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
        'paid_time' => null,
        'created_at' => '2026-05-01 11:00:00',
    ]);

    expect($this->action->handle($order))->toBe($session->id);
});

it('skips cancelled live sessions', function () {
    $account = PlatformAccount::factory()->create();
    LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'cancelled',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
    ]);

    expect($this->action->handle($order))->toBeNull();
});

it('picks the most recently started session when multiple overlap', function () {
    $account = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 09:00:00',
        'actual_end_at' => '2026-05-01 13:00:00',
        'status' => 'ended',
    ]);
    $later = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:30:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'ended',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
    ]);

    expect($this->action->handle($order))->toBe($later->id);
});

it('clears a stale match when no session window covers the reference time', function () {
    $account = PlatformAccount::factory()->create();
    $oldSession = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-04-01 10:00:00',
        'actual_end_at' => '2026-04-01 12:00:00',
        'status' => 'ended',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00', // way past the old session window
        'matched_live_session_id' => $oldSession->id,
    ]);

    expect($this->action->handle($order))->toBeNull();
    expect($order->fresh()->matched_live_session_id)->toBeNull();
});

it('does not issue an UPDATE when the order is already linked to the matched session', function () {
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

    $updateCount = 0;
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$updateCount) {
        if (str_starts_with(strtolower($query->sql), 'update')) {
            $updateCount++;
        }
    });

    $matched = $this->action->handle($order);

    expect($matched)->toBe($session->id);
    expect($updateCount)->toBe(0);
});
