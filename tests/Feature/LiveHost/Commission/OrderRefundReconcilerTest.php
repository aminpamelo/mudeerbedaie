<?php

use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\TiktokOrder;
use App\Models\TiktokReportImport;
use App\Models\User;
use App\Services\LiveHost\Tiktok\OrderRefundReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: build an order_list import anchored in April 2026.
 */
function makeOrderImport(User $pic): TiktokReportImport
{
    return TiktokReportImport::create([
        'report_type' => 'order_list',
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
 * point in time.
 */
function makeSessionCovering(\Carbon\Carbon $orderedAt, array $overrides = []): LiveSession
{
    return LiveSession::factory()->create(array_merge([
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
});

it('proposes a negative adjustment for orders with refund amount', function () {
    $createdAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = makeSessionCovering($createdAt);
    $import = makeOrderImport($this->pic);

    TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-REFUND-1',
        'order_status' => 'completed',
        'created_time' => $createdAt,
        'order_amount_myr' => 200,
        'order_refund_amount_myr' => 120,
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

it('proposes full refund for cancelled orders', function () {
    $createdAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = makeSessionCovering($createdAt);
    $import = makeOrderImport($this->pic);

    TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-CANCEL-1',
        'order_status' => 'cancelled',
        'created_time' => $createdAt,
        'cancelled_time' => $createdAt->copy()->addHour(),
        'order_amount_myr' => 150,
        'order_refund_amount_myr' => 0,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(1);

    $adjustment = LiveSessionGmvAdjustment::where('live_session_id', $session->id)->first();
    expect((float) $adjustment->amount_myr)->toBe(-150.0);
    expect($adjustment->status)->toBe('proposed');
});

it('skips orders matching multiple sessions (ambiguous)', function () {
    $createdAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');

    // Two overlapping sessions; both cover the order time.
    makeSessionCovering($createdAt);
    makeSessionCovering($createdAt, [
        'actual_start_at' => $createdAt->copy()->subMinutes(30),
        'actual_end_at' => $createdAt->copy()->addMinutes(30),
    ]);

    $import = makeOrderImport($this->pic);

    TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-AMBIG-1',
        'order_status' => 'completed',
        'created_time' => $createdAt,
        'order_amount_myr' => 100,
        'order_refund_amount_myr' => 100,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(0);
    expect($result['skipped_count'])->toBe(1);
    expect(LiveSessionGmvAdjustment::count())->toBe(0);
});

it('skips orders with no matching session', function () {
    $createdAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');

    // Session far away in time; no overlap.
    makeSessionCovering(\Carbon\Carbon::parse('2026-04-10 10:00:00'));

    $import = makeOrderImport($this->pic);

    TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-ORPHAN-1',
        'order_status' => 'completed',
        'created_time' => $createdAt,
        'order_amount_myr' => 80,
        'order_refund_amount_myr' => 80,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(0);
    expect($result['skipped_count'])->toBe(1);
    expect(LiveSessionGmvAdjustment::count())->toBe(0);
});

it('sets matched_live_session_id on tiktok_orders row', function () {
    $createdAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    $session = makeSessionCovering($createdAt);
    $import = makeOrderImport($this->pic);

    $order = TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-TRACE-1',
        'order_status' => 'completed',
        'created_time' => $createdAt,
        'order_amount_myr' => 200,
        'order_refund_amount_myr' => 75,
    ]);

    app(OrderRefundReconciler::class)->reconcile($import);

    $order->refresh();
    expect($order->matched_live_session_id)->toBe($session->id);
});

it('ignores non-refund non-cancelled orders entirely', function () {
    $createdAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    makeSessionCovering($createdAt);
    $import = makeOrderImport($this->pic);

    TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-HEALTHY-1',
        'order_status' => 'completed',
        'created_time' => $createdAt,
        'order_amount_myr' => 300,
        'order_refund_amount_myr' => 0,
    ]);

    $result = app(OrderRefundReconciler::class)->reconcile($import);

    expect($result['proposed_count'])->toBe(0);
    expect($result['skipped_count'])->toBe(0);
    expect(LiveSessionGmvAdjustment::count())->toBe(0);
});

it('is idempotent — rerunning does not double-propose', function () {
    $createdAt = \Carbon\Carbon::parse('2026-04-19 14:30:00');
    makeSessionCovering($createdAt);
    $import = makeOrderImport($this->pic);

    TiktokOrder::create([
        'import_id' => $import->id,
        'tiktok_order_id' => 'ORDER-IDEMPOTENT-1',
        'order_status' => 'completed',
        'created_time' => $createdAt,
        'order_amount_myr' => 200,
        'order_refund_amount_myr' => 50,
    ]);

    app(OrderRefundReconciler::class)->reconcile($import);
    app(OrderRefundReconciler::class)->reconcile($import);

    expect(LiveSessionGmvAdjustment::count())->toBe(1);
});
