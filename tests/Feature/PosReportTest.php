<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('monthly report returns 12 months with correct structure', function () {
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now()->startOfYear(),
        'total_amount' => 100.00,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/reports/monthly?year='.now()->year);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'year',
                'totals' => ['revenue', 'sales_count', 'items_sold'],
                'months',
            ],
        ])
        ->assertJsonCount(12, 'data.months');
});

test('monthly report only includes paid POS orders', function () {
    // Paid POS order — should count
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 100.00,
    ]);

    // Unpaid POS order — should NOT count
    ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => null,
        'order_date' => now(),
        'total_amount' => 50.00,
    ]);

    // Non-POS order — should NOT count
    ProductOrder::factory()->create([
        'source' => 'website',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 75.00,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/reports/monthly?year='.now()->year);

    $response->assertSuccessful();
    $data = $response->json('data');
    expect((float) $data['totals']['revenue'])->toBe(100.0);
    expect($data['totals']['sales_count'])->toBe(1);
});

test('daily report returns correct number of days for the month', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/reports/daily?year='.now()->year.'&month='.now()->month);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'year', 'month', 'month_name',
                'totals' => ['revenue', 'sales_count'],
                'days',
            ],
        ]);

    $daysInMonth = now()->daysInMonth;
    $response->assertJsonCount($daysInMonth, 'data.days');
});

test('daily report with day parameter returns item breakdown', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'paid_time' => now(),
        'order_date' => now(),
        'total_amount' => 200.00,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Test Product',
        'quantity_ordered' => 3,
        'unit_price' => 50.00,
        'total_price' => 150.00,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/pos/reports/daily?year='.now()->year.'&month='.now()->month.'&day='.now()->day);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'date', 'sales_count', 'revenue',
                'items',
                'orders',
            ],
        ]);

    expect($response->json('data.sales_count'))->toBe(1);
    expect($response->json('data.items.0.product_name'))->toBe('Test Product');
    expect($response->json('data.items.0.quantity'))->toBe(3);
});

test('report endpoints require authentication', function () {
    $this->getJson('/api/pos/reports/monthly')->assertUnauthorized();
    $this->getJson('/api/pos/reports/daily')->assertUnauthorized();
});
