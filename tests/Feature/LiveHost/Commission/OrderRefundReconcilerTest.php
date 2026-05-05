<?php

use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\ProductOrderPayment;
use App\Models\TiktokReportImport;
use App\Models\User;
use App\Services\LiveHost\Tiktok\OrderRefundReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: build a live_analysis import anchored in April 2026, scoped to the
 * given platform account. The reconciler scopes ProductOrder lookups by this
 * platform_account_id.
 */
function makeOrderImport(User $pic, PlatformAccount $account): TiktokReportImport
{
    return TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $account->id,
        'file_path' => 'tiktok-imports/test.xlsx',
        'uploaded_by' => $pic->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'completed',
    ]);
}

/**
 * Helper: build a verified, ended session whose live-window covers the given
 * point in time. Tied to the supplied platform account so the reconciler's
 * platform-scope filter accepts matches against it.
 */
function makeSessionCovering(\Carbon\Carbon $orderedAt, PlatformAccount $account, array $overrides = []): LiveSession
{
    return LiveSession::factory()->create(array_merge([
        'platform_account_id' => $account->id,
        'status' => 'ended',
        'scheduled_start_at' => $orderedAt->copy()->subHours(2),
        'actual_start_at' => $orderedAt->copy()->subHour(),
        'actual_end_at' => $orderedAt->copy()->addMinutes(10),
        'duration_minutes' => 70,
        'gmv_amount' => 500,
        'gmv_adjustment' => 0,
    ], $overrides));
}

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->account = PlatformAccount::factory()->create();
});

it('proposes a negative adjustment for orders with refunded payments', function () {
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = makeSessionCovering($paidAt, $this->account);
    $import = makeOrderImport($this->pic, $this->account);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $this->account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'ORDER-REFUND-1',
        'status' => 'refunded',
        'paid_time' => $paidAt,
        'total_amount' => 200,
    ]);

    ProductOrderPayment::create([
        'order_id' => $order->id,
        'payment_method' => 'tiktok_payment',
        'amount' => 120,
        'currency' => 'MYR',
        'status' => 'refunded',
        'refunded_at' => $paidAt->copy()->addHour(),
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(1);
    expect($result['skipped_count'])->toBe(0);

    $adjustment = LiveSessionGmvAdjustment::where('live_session_id', $session->id)->first();
    expect($adjustment)->not->toBeNull();
    expect((float) $adjustment->amount_myr)->toBe(-120.0);
    expect($adjustment->status)->toBe('proposed');
    expect($adjustment->adjusted_by)->toBeNull();
    expect($adjustment->reason)->toContain('ORDER-REFUND-1');

    // Proposed row must NOT feed the session's cached gmv_adjustment.
    $session->refresh();
    expect((float) $session->gmv_adjustment)->toBe(0.0);
});

it('proposes full refund for cancelled orders without explicit payment refund', function () {
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = makeSessionCovering($paidAt, $this->account);
    $import = makeOrderImport($this->pic, $this->account);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $this->account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'ORDER-CANCEL-1',
        'status' => 'cancelled',
        'paid_time' => $paidAt,
        'cancelled_at' => $paidAt->copy()->addHour(),
        'total_amount' => 150,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(1);

    $adjustment = LiveSessionGmvAdjustment::where('live_session_id', $session->id)->first();
    expect((float) $adjustment->amount_myr)->toBe(-150.0);
    expect($adjustment->status)->toBe('proposed');
});

it('uses cancelled_at to scope an order whose paid_time falls outside the import period', function () {
    $paidAt = \Carbon\Carbon::parse('2026-03-20 09:00:00'); // before period
    $cancelledAt = \Carbon\Carbon::parse('2026-04-05 12:00:00'); // inside period

    $session = makeSessionCovering($paidAt, $this->account);
    $import = makeOrderImport($this->pic, $this->account);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $this->account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'ORDER-LATE-CANCEL',
        'status' => 'cancelled',
        'paid_time' => $paidAt,
        'cancelled_at' => $cancelledAt,
        'total_amount' => 90,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(1);
});

it('skips orders without a matched_live_session_id', function () {
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $import = makeOrderImport($this->pic, $this->account);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $this->account->id,
        'matched_live_session_id' => null,
        'platform_order_id' => 'ORDER-ORPHAN-1',
        'status' => 'refunded',
        'paid_time' => $paidAt,
        'total_amount' => 80,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(0);
    expect(LiveSessionGmvAdjustment::count())->toBe(0);
});

it('skips orders outside the import period (paid_time and cancelled_at both outside)', function () {
    $outside = \Carbon\Carbon::parse('2026-05-15 14:30:00');
    $session = makeSessionCovering($outside, $this->account);
    $import = makeOrderImport($this->pic, $this->account);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $this->account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'ORDER-OUTSIDE-1',
        'status' => 'refunded',
        'paid_time' => $outside,
        'total_amount' => 80,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(0);
    expect(LiveSessionGmvAdjustment::count())->toBe(0);
});

it('ignores orders from a different platform_account', function () {
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $otherAccount = PlatformAccount::factory()->create();
    $session = makeSessionCovering($paidAt, $otherAccount);
    $import = makeOrderImport($this->pic, $this->account);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $otherAccount->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'ORDER-WRONG-SHOP',
        'status' => 'refunded',
        'paid_time' => $paidAt,
        'total_amount' => 100,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(0);
});

it('ignores non-refund non-cancelled orders entirely', function () {
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = makeSessionCovering($paidAt, $this->account);
    $import = makeOrderImport($this->pic, $this->account);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $this->account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'ORDER-HEALTHY-1',
        'status' => 'delivered',
        'paid_time' => $paidAt,
        'total_amount' => 300,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(0);
    expect(LiveSessionGmvAdjustment::count())->toBe(0);
});

it('is idempotent — rerunning does not double-propose', function () {
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = makeSessionCovering($paidAt, $this->account);
    $import = makeOrderImport($this->pic, $this->account);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $this->account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => 'ORDER-IDEMPOTENT-1',
        'status' => 'refunded',
        'paid_time' => $paidAt,
        'total_amount' => 200,
    ]);

    app(OrderRefundReconciler::class)->reconcile($import);
    app(OrderRefundReconciler::class)->reconcile($import);

    expect(LiveSessionGmvAdjustment::count())->toBe(1);
});

it('falls back to order_number when platform_order_id is missing', function () {
    $paidAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = makeSessionCovering($paidAt, $this->account);
    $import = makeOrderImport($this->pic, $this->account);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $this->account->id,
        'matched_live_session_id' => $session->id,
        'platform_order_id' => null,
        'order_number' => 'ORD-FALLBACK-1',
        'status' => 'refunded',
        'paid_time' => $paidAt,
        'total_amount' => 60,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(1);
    $adjustment = LiveSessionGmvAdjustment::first();
    expect($adjustment->reason)->toContain('ORD-FALLBACK-1');
});
