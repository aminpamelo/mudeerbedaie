<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\UpsellCommissionPayout;
use App\Models\User;
use App\Services\Upsell\CommissionPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('previews unpaid commission per teacher', function () {
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

    $rows = app(CommissionPayoutService::class)->preview('2026-05-01', '2026-05-31');

    expect($rows)->toHaveCount(1);
    expect($rows->first()['commission_total'])->toBe(100.0);
    expect($rows->first()['teacher_id'])->toBe($teacher->id);
    expect($rows->first()['session_count'])->toBe(1);
});

it('splits commission across multiple teachers in preview', function () {
    $teacherA = User::factory()->create();
    $teacherB = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacherA->id, $teacherB->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-05-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $rows = app(CommissionPayoutService::class)->preview('2026-05-01', '2026-05-31')->keyBy('teacher_id');

    expect($rows[$teacherA->id]['commission_total'])->toBe(50.0);
    expect($rows[$teacherB->id]['commission_total'])->toBe(50.0);
});

it('excludes pending product orders from preview', function () {
    $teacher = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-05-15',
    ]);
    $pending = ProductOrder::factory()->create(['payment_status' => 'pending']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $pending->id,
        'funnel_revenue' => 1000,
    ]);

    $rows = app(CommissionPayoutService::class)->preview('2026-05-01', '2026-05-31');

    expect($rows)->toBeEmpty();
});

it('excludes sessions outside the date range', function () {
    $teacher = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-04-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $rows = app(CommissionPayoutService::class)->preview('2026-05-01', '2026-05-31');

    expect($rows)->toBeEmpty();
});

it('excludes sessions already in an existing payout', function () {
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

    $existingPayout = UpsellCommissionPayout::factory()->create([
        'teacher_user_id' => $teacher->id,
        'status' => 'draft',
    ]);
    $existingPayout->sessions()->create([
        'class_session_id' => $session->id,
        'paid_revenue' => 1000,
        'commission_rate' => 10,
        'commission_amount' => 100,
    ]);

    $rows = app(CommissionPayoutService::class)->preview('2026-05-01', '2026-05-31');

    expect($rows)->toBeEmpty();
});

it('creates a payout from session ids', function () {
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

    $payout = app(CommissionPayoutService::class)->createPayout(
        $teacher->id,
        '2026-05-01',
        '2026-05-31',
        [$session->id]
    );

    expect($payout->status)->toBe('draft');
    expect((float) $payout->total_commission)->toBe(100.0);
    expect($payout->session_count)->toBe(1);
    expect($payout->sessions()->count())->toBe(1);

    $sessionRow = $payout->sessions()->first();
    expect((float) $sessionRow->paid_revenue)->toBe(1000.0);
    expect((float) $sessionRow->commission_rate)->toBe(10.0);
    expect((float) $sessionRow->commission_amount)->toBe(100.0);
});

it('splits payout commission across multiple teachers when creating', function () {
    $teacherA = User::factory()->create();
    $teacherB = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacherA->id, $teacherB->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-05-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $payout = app(CommissionPayoutService::class)->createPayout(
        $teacherA->id,
        '2026-05-01',
        '2026-05-31',
        [$session->id]
    );

    expect((float) $payout->total_commission)->toBe(50.0);
});

it('refuses to create a payout for a teacher not on the session', function () {
    $assignedTeacher = User::factory()->create();
    $outsider = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$assignedTeacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-05-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    expect(fn () => app(CommissionPayoutService::class)->createPayout(
        $outsider->id,
        '2026-05-01',
        '2026-05-31',
        [$session->id]
    ))->toThrow(InvalidArgumentException::class);
});

it('lock and markPaid follow state machine', function () {
    $payout = UpsellCommissionPayout::factory()->create(['status' => 'draft']);

    $payout->lock();
    expect($payout->fresh()->status)->toBe('locked');
    expect($payout->fresh()->locked_at)->not->toBeNull();

    $accountant = User::factory()->create();
    $payout->markPaid($accountant->id, 'TXN-12345');
    expect($payout->fresh())
        ->status->toBe('paid')
        ->payment_reference->toBe('TXN-12345')
        ->paid_by_user_id->toBe($accountant->id);
});

it('lock fails if not draft', function () {
    $payout = UpsellCommissionPayout::factory()->locked()->create();
    expect(fn () => $payout->lock())->toThrow(InvalidArgumentException::class);
});

it('markPaid fails if not locked', function () {
    $payout = UpsellCommissionPayout::factory()->create(['status' => 'draft']);
    $accountant = User::factory()->create();
    expect(fn () => $payout->markPaid($accountant->id, 'TXN-001'))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes status scopes', function () {
    UpsellCommissionPayout::factory()->create(['status' => 'draft']);
    UpsellCommissionPayout::factory()->locked()->create();
    UpsellCommissionPayout::factory()->paid()->create();

    expect(UpsellCommissionPayout::draft()->count())->toBe(1);
    expect(UpsellCommissionPayout::locked()->count())->toBe(1);
    expect(UpsellCommissionPayout::paid()->count())->toBe(1);
});
