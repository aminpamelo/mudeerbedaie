<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\UpsellCommissionPayout;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->accountant = User::factory()->create(['role' => 'accountant']);
});

it('loads the preview when accountant clicks Load Preview', function () {
    $teacher = User::factory()->create(['name' => 'Prof X']);
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

    $this->actingAs($this->accountant);
    Volt::test('admin.upsell-commission-payouts')
        ->set('from', '2026-05-01')
        ->set('to', '2026-05-31')
        ->call('loadPreview')
        ->assertSee('Prof X')
        ->assertSee('100.00');
});

it('creates a draft payout when accountant clicks Create Payout', function () {
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

    $this->actingAs($this->accountant);
    Volt::test('admin.upsell-commission-payouts')
        ->set('from', '2026-05-01')
        ->set('to', '2026-05-31')
        ->call('loadPreview')
        ->call('createPayout', $teacher->id);

    expect(UpsellCommissionPayout::count())->toBe(1);
    expect(UpsellCommissionPayout::first()->status)->toBe('draft');
});

it('locks a draft payout', function () {
    $payout = UpsellCommissionPayout::factory()->create(['status' => 'draft']);

    $this->actingAs($this->accountant);
    Volt::test('admin.upsell-commission-payouts')
        ->call('lock', $payout->id);

    expect($payout->fresh()->status)->toBe('locked');
});

it('marks a locked payout as paid with reference', function () {
    $payout = UpsellCommissionPayout::factory()->create(['status' => 'locked', 'locked_at' => now()]);

    $this->actingAs($this->accountant);
    Volt::test('admin.upsell-commission-payouts')
        ->call('startMarkPaid', $payout->id)
        ->set('paymentReference', 'TXN-12345')
        ->call('confirmMarkPaid');

    expect($payout->fresh())
        ->status->toBe('paid')
        ->payment_reference->toBe('TXN-12345');
});

it('requires a payment reference to mark paid', function () {
    $payout = UpsellCommissionPayout::factory()->create(['status' => 'locked', 'locked_at' => now()]);

    $this->actingAs($this->accountant);
    Volt::test('admin.upsell-commission-payouts')
        ->call('startMarkPaid', $payout->id)
        ->set('paymentReference', '')
        ->call('confirmMarkPaid')
        ->assertHasErrors(['paymentReference']);

    expect($payout->fresh()->status)->toBe('locked'); // unchanged
});

it('forbids student from the page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get(route('admin.upsell-commissions'))
        ->assertForbidden();
});
