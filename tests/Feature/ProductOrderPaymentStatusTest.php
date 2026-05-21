<?php

use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has the payment_status column on product_orders', function () {
    expect(Schema::hasColumn('product_orders', 'payment_status'))->toBeTrue();
});

it('has the payment confirmation tracking columns', function () {
    expect(Schema::hasColumns('product_orders', [
        'payment_status',
        'payment_confirmed_by_user_id',
        'payment_confirmed_at',
        'payment_rejection_reason',
    ]))->toBeTrue();
});

it('defaults payment_status to pending for new orders', function () {
    $order = ProductOrder::factory()->create();

    expect($order->fresh()->payment_status)->toBe('pending');
});

it('persists payment_status via Eloquent mass assignment', function () {
    $order = ProductOrder::create([
        'order_number' => 'MASS-001',
        'currency' => 'MYR',
        'subtotal' => 100, 'shipping_cost' => 0, 'tax_amount' => 0,
        'total_amount' => 100, 'discount_amount' => 0,
        'order_date' => now(),
        'status' => 'pending',
        'payment_status' => 'paid',
    ]);

    expect($order->fresh()->payment_status)->toBe('paid');
});

it('casts payment_confirmed_at to a Carbon instance', function () {
    $order = ProductOrder::factory()->create([
        'payment_confirmed_at' => now(),
    ]);

    expect($order->fresh()->payment_confirmed_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('persists payment_status when explicitly set via DB insert', function () {
    DB::table('product_orders')->insert([
        'order_number' => 'PO-TEST-PS-001',
        'status' => 'confirmed',
        'currency' => 'MYR',
        'subtotal' => 100,
        'shipping_cost' => 0,
        'tax_amount' => 0,
        'total_amount' => 100,
        'discount_amount' => 0,
        'order_date' => now(),
        'created_at' => now(),
        'updated_at' => now(),
        'payment_status' => 'paid',
    ]);

    $row = DB::table('product_orders')->where('order_number', 'PO-TEST-PS-001')->first();
    expect($row->payment_status)->toBe('paid');
});

it('applies the backfill rules consistently when run against fresh rows', function () {
    // Simulate pre-migration rows by inserting with default payment_status='pending',
    // then run the same SQL the migration uses and verify results.
    $rows = [
        ['order_number' => 'PO-BF-001', 'status' => 'confirmed', 'paid_time' => null, 'expected' => 'paid'],
        ['order_number' => 'PO-BF-002', 'status' => 'processing', 'paid_time' => null, 'expected' => 'paid'],
        ['order_number' => 'PO-BF-003', 'status' => 'partially_shipped', 'paid_time' => null, 'expected' => 'paid'],
        ['order_number' => 'PO-BF-004', 'status' => 'shipped', 'paid_time' => null, 'expected' => 'paid'],
        ['order_number' => 'PO-BF-005', 'status' => 'delivered', 'paid_time' => null, 'expected' => 'paid'],
        ['order_number' => 'PO-BF-006', 'status' => 'pending', 'paid_time' => now(), 'expected' => 'paid'],
        ['order_number' => 'PO-BF-007', 'status' => 'cancelled', 'paid_time' => null, 'expected' => 'failed'],
        ['order_number' => 'PO-BF-008', 'status' => 'failed', 'paid_time' => null, 'expected' => 'failed'],
        ['order_number' => 'PO-BF-009', 'status' => 'refunded', 'paid_time' => null, 'expected' => 'refunded'],
        ['order_number' => 'PO-BF-010', 'status' => 'pending', 'paid_time' => null, 'expected' => 'pending'],
        ['order_number' => 'PO-BF-011', 'status' => 'completed', 'paid_time' => null, 'expected' => 'paid'],
        ['order_number' => 'PO-BF-012', 'status' => 'returned', 'paid_time' => null, 'expected' => 'refunded'],
    ];

    foreach ($rows as $row) {
        DB::table('product_orders')->insert([
            'order_number' => $row['order_number'],
            'status' => $row['status'],
            'paid_time' => $row['paid_time'],
            'currency' => 'MYR',
            'subtotal' => 100,
            'shipping_cost' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'discount_amount' => 0,
            'order_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Re-run the same backfill that the migration performs.
    DB::table('product_orders')
        ->where(function ($q) {
            $q->whereIn('status', ['confirmed', 'processing', 'partially_shipped', 'shipped', 'delivered'])
                ->orWhereNotNull('paid_time');
        })
        ->update(['payment_status' => 'paid']);

    DB::table('product_orders')
        ->whereIn('status', ['cancelled', 'failed'])
        ->update(['payment_status' => 'failed']);

    DB::table('product_orders')
        ->where('status', 'refunded')
        ->update(['payment_status' => 'refunded']);

    // Follow-up backfill (2026_05_20_000002) for statuses missed by the first
    // migration: completed → paid, returned → refunded.
    DB::table('product_orders')
        ->where('status', 'completed')
        ->where('payment_status', 'pending')
        ->update(['payment_status' => 'paid']);

    DB::table('product_orders')
        ->where('status', 'returned')
        ->where('payment_status', 'pending')
        ->update(['payment_status' => 'refunded']);

    foreach ($rows as $row) {
        $actual = DB::table('product_orders')->where('order_number', $row['order_number'])->value('payment_status');
        expect($actual)->toBe($row['expected'], "Row {$row['order_number']} with status={$row['status']} should be {$row['expected']}");
    }
});
