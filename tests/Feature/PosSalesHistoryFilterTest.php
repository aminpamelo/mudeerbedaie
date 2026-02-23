<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('sales history can filter by status paid', function () {
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => null,
        'status' => 'pending',
        'order_date' => now(),
        'total_amount' => 50.00,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/sales?status=paid');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.paid_time'))->not->toBeNull();
});

test('sales history can filter by status pending', function () {
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => null,
        'status' => 'pending',
        'order_date' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/sales?status=pending');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.paid_time'))->toBeNull();
});

test('sales history can filter by status cancelled', function () {
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => null,
        'status' => 'cancelled',
        'order_date' => now(),
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/sales?status=cancelled');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('cancelled');
});

test('sales history can filter by payment method', function () {
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'payment_method' => 'cash',
        'order_date' => now(),
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'payment_method' => 'card',
        'order_date' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/sales?payment_method=cash');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.payment_method'))->toBe('cash');
});

test('sales history can filter by period today', function () {
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
    ]);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now()->subDays(5),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/sales?period=today');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

test('sales history can combine multiple filters', function () {
    // Paid + cash + today — should match
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'payment_method' => 'cash',
        'order_date' => now(),
    ]);

    // Paid + card + today — should NOT match (wrong payment method)
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'payment_method' => 'card',
        'order_date' => now(),
    ]);

    // Paid + cash + old — should NOT match (wrong period)
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'payment_method' => 'cash',
        'order_date' => now()->subMonth(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/sales?status=paid&payment_method=cash&period=today');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});
