<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\UpsellCommissionPayout;
use App\Models\User;
use App\Services\Upsell\UpsellPaidOrdersQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('only returns paid funnel orders', function () {
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [1],
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    $pending = ProductOrder::factory()->create(['payment_status' => 'pending']);

    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $pending->id,
        'funnel_revenue' => 200,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)->get();

    expect($rows)->toHaveCount(1);
});

it('excludes orders whose session has no upsell_funnel_ids', function () {
    $sessionWithUpsell = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [1],
    ]);
    $sessionWithoutUpsell = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => null,
        'upsell_teacher_ids' => null,
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);

    FunnelOrder::factory()->create([
        'class_session_id' => $sessionWithUpsell->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $sessionWithoutUpsell->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 200,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)->get();

    expect($rows)->toHaveCount(1);
});

it('filters by date range', function () {
    $inRange = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [1],
        'session_date' => '2026-05-15',
    ]);
    $outOfRange = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [1],
        'session_date' => '2026-03-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $inRange->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $outOfRange->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)
        ->forDateRange('2026-05-01', '2026-05-31')
        ->get();

    expect($rows)->toHaveCount(1);
});

it('filters by funnel id', function () {
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [7],
        'upsell_teacher_ids' => [1],
    ]);
    $otherSession = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [99],
        'upsell_teacher_ids' => [1],
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);

    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $otherSession->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)
        ->forFunnelId(7)
        ->get();

    expect($rows)->toHaveCount(1);
});

it('filters by pic id', function () {
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_pic_user_ids' => [42],
        'upsell_teacher_ids' => [1],
    ]);
    $otherSession = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_pic_user_ids' => [99],
        'upsell_teacher_ids' => [1],
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);

    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $otherSession->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)
        ->forPicId(42)
        ->get();

    expect($rows)->toHaveCount(1);
});

it('groups by teacher with single teacher', function () {
    $teacher = User::factory()->create(['name' => 'Teacher A']);
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)->byTeacher();

    expect($rows)->toHaveCount(1);
    expect($rows->first()['teacher_id'])->toBe($teacher->id);
    expect($rows->first()['teacher_name'])->toBe('Teacher A');
    expect($rows->first()['paid_orders'])->toBe(1);
    expect($rows->first()['sessions_count'])->toBe(1);
    expect((float) $rows->first()['paid_revenue'])->toBe(1000.0);
    expect((float) $rows->first()['commission_earned'])->toBe(100.0);
});

it('splits commission across multiple teachers on a session', function () {
    $teacher1 = User::factory()->create();
    $teacher2 = User::factory()->create();
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher1->id, $teacher2->id],
        'upsell_teacher_commission_rate' => 10,
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)->byTeacher()->keyBy('teacher_id');

    expect($rows)->toHaveCount(2);
    expect((float) $rows[$teacher1->id]['commission_earned'])->toBe(50.0);
    expect((float) $rows[$teacher2->id]['commission_earned'])->toBe(50.0);
    expect((float) $rows[$teacher1->id]['paid_revenue'])->toBe(500.0);
    expect((float) $rows[$teacher2->id]['paid_revenue'])->toBe(500.0);
});

it('skips funnel orders whose session has no upsell teachers in byTeacher', function () {
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => null,
        'upsell_teacher_commission_rate' => 10,
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 500,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)->byTeacher();

    expect($rows)->toHaveCount(0);
});

it('includes commission_paid from paid payouts in byTeacher', function () {
    $teacher = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-05-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $payout = UpsellCommissionPayout::factory()->paid()->create([
        'teacher_user_id' => $teacher->id,
    ]);
    $payout->sessions()->create([
        'class_session_id' => $session->id,
        'paid_revenue' => 1000,
        'commission_rate' => 10,
        'commission_amount' => 100,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)
        ->forDateRange('2026-05-01', '2026-05-31')
        ->byTeacher();

    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()['commission_earned'])->toBe(100.0);
    expect((float) $rows->first()['commission_paid'])->toBe(100.0);
    expect((float) $rows->first()['commission_pending'])->toBe(0.0);
});

it('shows pending commission when no payout exists in byTeacher', function () {
    $teacher = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-05-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)
        ->forDateRange('2026-05-01', '2026-05-31')
        ->byTeacher();

    expect((float) $rows->first()['commission_earned'])->toBe(100.0);
    expect((float) $rows->first()['commission_paid'])->toBe(0.0);
    expect((float) $rows->first()['commission_pending'])->toBe(100.0);
});

it('does not count draft or locked payouts as paid in byTeacher', function () {
    $teacher = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-05-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $draftPayout = UpsellCommissionPayout::factory()->draft()->create([
        'teacher_user_id' => $teacher->id,
    ]);
    $draftPayout->sessions()->create([
        'class_session_id' => $session->id,
        'paid_revenue' => 1000,
        'commission_rate' => 10,
        'commission_amount' => 100,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)
        ->forDateRange('2026-05-01', '2026-05-31')
        ->byTeacher();

    expect((float) $rows->first()['commission_paid'])->toBe(0.0);
    expect((float) $rows->first()['commission_pending'])->toBe(100.0);
});

it('groups by product with line type from funnel order', function () {
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [1],
        'upsell_teacher_commission_rate' => 10,
    ]);

    $mainProduct = Product::factory()->create();
    $bumpProduct = Product::factory()->create();

    $mainOrder = ProductOrder::factory()->create(['payment_status' => 'paid']);
    ProductOrderItem::factory()->create([
        'order_id' => $mainOrder->id,
        'product_id' => $mainProduct->id,
        'product_name' => 'Main Course',
        'quantity_ordered' => 2,
        'total_price' => 200,
    ]);

    $bumpOrder = ProductOrder::factory()->create(['payment_status' => 'paid']);
    ProductOrderItem::factory()->create([
        'order_id' => $bumpOrder->id,
        'product_id' => $bumpProduct->id,
        'product_name' => 'Bump Product',
        'quantity_ordered' => 1,
        'total_price' => 50,
    ]);

    FunnelOrder::factory()->create([
        'funnel_id' => 1,
        'class_session_id' => $session->id,
        'product_order_id' => $mainOrder->id,
        'order_type' => 'main',
        'funnel_revenue' => 200,
    ]);
    FunnelOrder::factory()->create([
        'funnel_id' => 1,
        'class_session_id' => $session->id,
        'product_order_id' => $bumpOrder->id,
        'order_type' => 'bump',
        'funnel_revenue' => 50,
    ]);

    $rows = app(UpsellPaidOrdersQuery::class)->byProduct();

    expect($rows)->toHaveCount(2);

    $byId = $rows->keyBy('product_id');
    expect($byId[$mainProduct->id]['product_name'])->toBe('Main Course');
    expect($byId[$mainProduct->id]['line_type'])->toBe('main');
    expect($byId[$mainProduct->id]['units'])->toBe(2);
    expect((float) $byId[$mainProduct->id]['revenue'])->toBe(200.0);

    expect($byId[$bumpProduct->id]['product_name'])->toBe('Bump Product');
    expect($byId[$bumpProduct->id]['line_type'])->toBe('bump');
    expect($byId[$bumpProduct->id]['units'])->toBe(1);
    expect((float) $byId[$bumpProduct->id]['revenue'])->toBe(50.0);

    // Sorted by revenue descending
    expect($rows->first()['product_id'])->toBe($mainProduct->id);
});

it('excludes paid orders that have been returned, cancelled, or refunded', function () {
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [1],
        'upsell_teacher_commission_rate' => 20,
    ]);

    // The "good" paid order — should count.
    $goodOrder = ProductOrder::factory()->create([
        'payment_status' => 'paid',
        'status' => 'delivered',
    ]);

    // Three "dirty paid" orders: payment cleared but order is no longer fulfillable.
    // These can exist on legacy rows where payment_status wasn't flipped, so the
    // query must defensively exclude them by `status`.
    $returnedOrder = ProductOrder::factory()->create([
        'payment_status' => 'paid',
        'status' => 'returned',
    ]);
    $cancelledOrder = ProductOrder::factory()->create([
        'payment_status' => 'paid',
        'status' => 'cancelled',
    ]);
    $refundedOrder = ProductOrder::factory()->create([
        'payment_status' => 'paid',
        'status' => 'refunded',
    ]);

    foreach ([$goodOrder, $returnedOrder, $cancelledOrder, $refundedOrder] as $order) {
        FunnelOrder::factory()->create([
            'class_session_id' => $session->id,
            'product_order_id' => $order->id,
            'funnel_revenue' => 100,
        ]);
    }

    $rows = app(UpsellPaidOrdersQuery::class)->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->product_order_id)->toBe($goodOrder->id);
});
