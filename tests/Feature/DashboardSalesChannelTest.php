<?php

use App\Models\Platform;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Reports\SalesChannelDashboard;

use function Pest\Laravel\actingAs;

it('renders the e-commerce dashboard for an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('E-commerce Sales')
        ->assertSee('Sales by Channel')
        ->assertSee('Monthly Revenue Trend');
});

it('attributes revenue to the right channel and matches the report rules', function () {
    $year = (int) date('Y');
    $orderDate = now()->startOfYear()->addMonth();
    $platform = Platform::factory()->create();

    // A delivered platform order counts toward revenue + Platform channel.
    ProductOrder::factory()->create([
        'order_date' => $orderDate,
        'status' => 'delivered',
        'total_amount' => 300,
        'platform_id' => $platform->id,
        'source' => 'platform',
    ]);

    // A POS order (company-owned) → POS channel.
    ProductOrder::factory()->create([
        'order_date' => $orderDate,
        'status' => 'processing',
        'total_amount' => 100,
        'platform_id' => null,
        'source' => 'pos',
    ]);

    // A cancelled order is excluded from revenue entirely.
    ProductOrder::factory()->create([
        'order_date' => $orderDate,
        'status' => 'cancelled',
        'total_amount' => 999,
        'platform_id' => $platform->id,
        'source' => 'platform',
    ]);

    $service = new SalesChannelDashboard($year);
    $overview = $service->overview();
    $breakdown = collect($service->sourceBreakdown())->keyBy('key');

    expect($overview['total_revenue'])->toBe(400.0)
        ->and($overview['total_orders'])->toBe(2)
        ->and($breakdown['platform']['revenue'])->toBe(300.0)
        ->and($breakdown['platform']['orders'])->toBe(1)
        ->and($breakdown['pos']['revenue'])->toBe(100.0)
        ->and($breakdown['pos']['orders'])->toBe(1);
});

it('hides admin-hidden orders from the dashboard figures', function () {
    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 500,
        'hidden_from_admin' => true,
    ]);

    expect((new SalesChannelDashboard)->overview()['total_revenue'])->toBe(0.0);
});
