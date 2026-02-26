<?php

use App\Models\ProductOrder;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
});

test('can update pos sale status to paid', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'status' => 'pending',
        'paid_time' => null,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $this->user->id],
    ]);

    $order->payments()->create([
        'payment_method' => 'cash',
        'amount' => $order->total_amount,
        'currency' => 'MYR',
        'status' => 'pending',
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/status", ['status' => 'paid'])
        ->assertSuccessful()
        ->assertJsonPath('data.paid_time', fn ($v) => $v !== null);

    $order->refresh();
    expect($order->paid_time)->not->toBeNull();
    expect($order->payments()->first()->status)->toBe('completed');
});

test('can update pos sale status to cancelled', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'status' => 'pending',
        'paid_time' => null,
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $this->user->id],
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/status", ['status' => 'cancelled'])
        ->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe('cancelled');
});

test('can update pos sale status to pending', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'status' => 'confirmed',
        'paid_time' => now(),
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $this->user->id],
    ]);

    $order->payments()->create([
        'payment_method' => 'cash',
        'amount' => $order->total_amount,
        'currency' => 'MYR',
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/status", ['status' => 'pending'])
        ->assertSuccessful();

    $order->refresh();
    expect($order->paid_time)->toBeNull();
    expect($order->status)->toBe('pending');
    expect($order->payments()->first()->status)->toBe('pending');
});

test('can delete a pos sale', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'metadata' => ['pos_sale' => true, 'salesperson_id' => $this->user->id],
    ]);

    $order->payments()->create([
        'payment_method' => 'cash',
        'amount' => $order->total_amount,
        'currency' => 'MYR',
        'status' => 'completed',
    ]);

    actingAs($this->user)
        ->deleteJson("/api/pos/sales/{$order->id}")
        ->assertSuccessful();

    expect(ProductOrder::find($order->id))->toBeNull();
});

test('cannot update non-pos sale status', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'website',
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/status", ['status' => 'paid'])
        ->assertForbidden();
});

test('cannot delete non-pos sale', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'website',
    ]);

    actingAs($this->user)
        ->deleteJson("/api/pos/sales/{$order->id}")
        ->assertForbidden();
});

test('rejects invalid status value', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'metadata' => ['pos_sale' => true],
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/status", ['status' => 'invalid'])
        ->assertUnprocessable();
});
