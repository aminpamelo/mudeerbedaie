<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates all 6 commission supporting tables', function () {
    foreach ([
        'live_session_gmv_adjustments',
        'live_host_payroll_runs',
        'live_host_payroll_items',
        'tiktok_report_imports',
        'tiktok_live_reports',
        'tiktok_orders',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("expected {$table} to exist");
    }
});

it('has expected columns on live_session_gmv_adjustments', function () {
    foreach (['live_session_id', 'amount_myr', 'reason', 'adjusted_by', 'adjusted_at'] as $col) {
        expect(Schema::hasColumn('live_session_gmv_adjustments', $col))->toBeTrue($col);
    }
});

it('has expected columns on payroll runs + items', function () {
    foreach (['period_start', 'period_end', 'cutoff_date', 'status', 'locked_at', 'paid_at'] as $col) {
        expect(Schema::hasColumn('live_host_payroll_runs', $col))->toBeTrue($col);
    }
    foreach ([
        'payroll_run_id', 'user_id', 'base_salary_myr', 'gmv_commission_myr',
        'override_l1_myr', 'override_l2_myr', 'net_payout_myr', 'calculation_breakdown_json',
    ] as $col) {
        expect(Schema::hasColumn('live_host_payroll_items', $col))->toBeTrue($col);
    }
});

it('has expected columns on tiktok import tables', function () {
    foreach (['report_type', 'file_path', 'status', 'total_rows'] as $col) {
        expect(Schema::hasColumn('tiktok_report_imports', $col))->toBeTrue($col);
    }
    foreach (['tiktok_creator_id', 'launched_time', 'gmv_myr', 'matched_live_session_id', 'raw_row_json'] as $col) {
        expect(Schema::hasColumn('tiktok_live_reports', $col))->toBeTrue($col);
    }
    foreach (['tiktok_order_id', 'order_status', 'order_refund_amount_myr', 'cancelled_time', 'matched_live_session_id'] as $col) {
        expect(Schema::hasColumn('tiktok_orders', $col))->toBeTrue($col);
    }
});

it('enforces uniqueness on payroll_items(payroll_run_id, user_id)', function () {
    $user = User::factory()->create(['role' => 'live_host']);

    $runId = DB::table('live_host_payroll_runs')->insertGetId([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-15',
        'cutoff_date' => '2026-04-29',
        'status' => 'draft',
        'locked_at' => null,
        'locked_by' => null,
        'paid_at' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('live_host_payroll_items')->insert([
        'payroll_run_id' => $runId,
        'user_id' => $user->id,
        'base_salary_myr' => 2000.00,
        'sessions_count' => 10,
        'total_per_live_myr' => 300.00,
        'total_gmv_myr' => 10000.00,
        'total_gmv_adjustment_myr' => 0,
        'net_gmv_myr' => 10000.00,
        'gmv_commission_myr' => 400.00,
        'override_l1_myr' => 0,
        'override_l2_myr' => 0,
        'gross_total_myr' => 2700.00,
        'deductions_myr' => 0,
        'net_payout_myr' => 2700.00,
        'calculation_breakdown_json' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('live_host_payroll_items')->insert([
        'payroll_run_id' => $runId,
        'user_id' => $user->id,
        'base_salary_myr' => 2500.00,
        'sessions_count' => 11,
        'total_per_live_myr' => 330.00,
        'total_gmv_myr' => 11000.00,
        'total_gmv_adjustment_myr' => 0,
        'net_gmv_myr' => 11000.00,
        'gmv_commission_myr' => 440.00,
        'override_l1_myr' => 0,
        'override_l2_myr' => 0,
        'gross_total_myr' => 3270.00,
        'deductions_myr' => 0,
        'net_payout_myr' => 3270.00,
        'calculation_breakdown_json' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('enforces uniqueness on tiktok_orders.tiktok_order_id', function () {
    $uploader = User::factory()->create();

    $importId = DB::table('tiktok_report_imports')->insertGetId([
        'report_type' => 'order_list',
        'file_path' => 'imports/tiktok/orders.xlsx',
        'uploaded_by' => $uploader->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-15',
        'status' => 'completed',
        'total_rows' => 2,
        'matched_rows' => 0,
        'unmatched_rows' => 0,
        'error_log_json' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('tiktok_orders')->insert([
        'import_id' => $importId,
        'tiktok_order_id' => 'ORD-12345',
        'order_status' => 'delivered',
        'order_substatus' => null,
        'cancelation_return_type' => null,
        'created_time' => now(),
        'paid_time' => now(),
        'rts_time' => null,
        'shipped_time' => null,
        'delivered_time' => null,
        'cancelled_time' => null,
        'order_amount_myr' => 100.00,
        'order_refund_amount_myr' => 0,
        'payment_method' => 'card',
        'fulfillment_type' => 'seller',
        'product_category' => null,
        'matched_live_session_id' => null,
        'raw_row_json' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('tiktok_orders')->insert([
        'import_id' => $importId,
        'tiktok_order_id' => 'ORD-12345',
        'order_status' => 'delivered',
        'order_substatus' => null,
        'cancelation_return_type' => null,
        'created_time' => now(),
        'paid_time' => now(),
        'rts_time' => null,
        'shipped_time' => null,
        'delivered_time' => null,
        'cancelled_time' => null,
        'order_amount_myr' => 200.00,
        'order_refund_amount_myr' => 0,
        'payment_method' => 'card',
        'fulfillment_type' => 'seller',
        'product_category' => null,
        'matched_live_session_id' => null,
        'raw_row_json' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
