<?php

use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->accountant = User::factory()->create(['role' => 'accountant']);

    $this->cod_order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
    ]);
});

it('shows the approval card to accountants for pending COD funnel orders', function () {
    $this->actingAs($this->accountant);

    Volt::test('admin.orders.payment-approval-card', ['order' => $this->cod_order])
        ->assertSee('Approve Payment');
});

it('hides the action buttons from non-accountants', function () {
    $regular = User::factory()->create(['role' => 'student']);
    $this->actingAs($regular);

    Volt::test('admin.orders.payment-approval-card', ['order' => $this->cod_order])
        ->assertDontSee('Approve Payment');
});

it('requires a receipt to approve', function () {
    $this->actingAs($this->accountant);

    Volt::test('admin.orders.payment-approval-card', ['order' => $this->cod_order])
        ->call('approve')
        ->assertHasErrors(['receipt']);
});

it('approves the order with a valid receipt', function () {
    $this->actingAs($this->accountant);
    $receipt = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

    Volt::test('admin.orders.payment-approval-card', ['order' => $this->cod_order])
        ->set('receipt', $receipt)
        ->call('approve')
        ->assertHasNoErrors();

    expect($this->cod_order->fresh())
        ->payment_status->toBe('paid')
        ->payment_confirmed_by_user_id->toBe($this->accountant->id);
});

it('requires a reason to reject', function () {
    $this->actingAs($this->accountant);

    Volt::test('admin.orders.payment-approval-card', ['order' => $this->cod_order])
        ->set('rejectionReason', '')
        ->call('reject')
        ->assertHasErrors(['rejectionReason']);
});

it('rejects the order with a valid reason', function () {
    $this->actingAs($this->accountant);

    Volt::test('admin.orders.payment-approval-card', ['order' => $this->cod_order])
        ->set('rejectionReason', 'No transfer received within 7 days')
        ->call('reject')
        ->assertHasNoErrors();

    expect($this->cod_order->fresh())
        ->payment_status->toBe('failed')
        ->payment_rejection_reason->toBe('No transfer received within 7 days');
});

it('does not show action card for orders that are already paid', function () {
    $paid_order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'payment_method' => 'cod',
        'payment_status' => 'paid',
        'payment_confirmed_by_user_id' => $this->accountant->id,
        'payment_confirmed_at' => now(),
    ]);
    $this->actingAs($this->accountant);

    Volt::test('admin.orders.payment-approval-card', ['order' => $paid_order])
        ->assertDontSee('Approve Payment')
        ->assertSee($this->accountant->name);
});
