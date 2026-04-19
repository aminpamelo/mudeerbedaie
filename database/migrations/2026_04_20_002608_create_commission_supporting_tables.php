<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the six supporting tables for the Live Host Commission system:
     *  1. live_session_gmv_adjustments — PIC-proposed adjustments against a session's GMV
     *  2. live_host_payroll_runs       — a bi-monthly payroll cycle
     *  3. live_host_payroll_items      — per-host payout line within a run
     *  4. tiktok_report_imports        — audit record for each TikTok xlsx upload
     *  5. tiktok_live_reports          — one row per row in Live Analysis.xlsx
     *  6. tiktok_orders                — one row per row in All order.xlsx
     *
     * Ordering matters: parent tables come before child tables so FKs resolve.
     */
    public function up(): void
    {
        Schema::create('live_session_gmv_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_myr', 10, 2);
            $table->string('reason');
            $table->foreignId('adjusted_by')->constrained('users');
            $table->timestamp('adjusted_at');
            $table->timestamps();

            $table->index('live_session_id');
        });

        Schema::create('live_host_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('cutoff_date');
            $table->string('status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
            $table->index('status');
        });

        Schema::create('live_host_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('live_host_payroll_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('base_salary_myr', 10, 2)->default(0);
            $table->integer('sessions_count')->default(0);
            $table->decimal('total_per_live_myr', 10, 2)->default(0);
            $table->decimal('total_gmv_myr', 12, 2)->default(0);
            $table->decimal('total_gmv_adjustment_myr', 12, 2)->default(0);
            $table->decimal('net_gmv_myr', 12, 2)->default(0);
            $table->decimal('gmv_commission_myr', 10, 2)->default(0);
            $table->decimal('override_l1_myr', 10, 2)->default(0);
            $table->decimal('override_l2_myr', 10, 2)->default(0);
            $table->decimal('gross_total_myr', 10, 2)->default(0);
            $table->decimal('deductions_myr', 10, 2)->default(0);
            $table->decimal('net_payout_myr', 10, 2)->default(0);
            $table->json('calculation_breakdown_json')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'user_id'], 'uniq_payroll_run_user');
        });

        Schema::create('tiktok_report_imports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('uploaded_at');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('matched_rows')->default(0);
            $table->integer('unmatched_rows')->default(0);
            $table->json('error_log_json')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'status']);
        });

        Schema::create('tiktok_live_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('tiktok_report_imports')->cascadeOnDelete();
            $table->string('tiktok_creator_id')->index();
            $table->string('creator_nickname')->nullable();
            $table->string('creator_display_name')->nullable();
            $table->timestamp('launched_time')->index();
            $table->integer('duration_seconds')->nullable();
            $table->decimal('gmv_myr', 12, 2)->default(0);
            $table->decimal('live_attributed_gmv_myr', 12, 2)->default(0);
            $table->integer('products_added')->default(0);
            $table->integer('products_sold')->default(0);
            $table->integer('sku_orders')->default(0);
            $table->integer('items_sold')->default(0);
            $table->integer('unique_customers')->default(0);
            $table->decimal('avg_price_myr', 10, 2)->default(0);
            $table->decimal('click_to_order_rate', 6, 2)->default(0);
            $table->integer('viewers')->default(0);
            $table->integer('views')->default(0);
            $table->integer('avg_view_duration_sec')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('likes')->default(0);
            $table->integer('new_followers')->default(0);
            $table->integer('product_impressions')->default(0);
            $table->integer('product_clicks')->default(0);
            $table->decimal('ctr', 6, 2)->default(0);
            $table->foreignId('matched_live_session_id')->nullable()->constrained('live_sessions')->nullOnDelete();
            $table->json('raw_row_json')->nullable();
            $table->timestamps();

            $table->index('matched_live_session_id');
        });

        Schema::create('tiktok_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('tiktok_report_imports')->cascadeOnDelete();
            $table->string('tiktok_order_id')->unique();
            $table->string('order_status')->nullable();
            $table->string('order_substatus')->nullable();
            $table->string('cancelation_return_type')->nullable();
            $table->timestamp('created_time')->nullable();
            $table->timestamp('paid_time')->nullable();
            $table->timestamp('rts_time')->nullable();
            $table->timestamp('shipped_time')->nullable();
            $table->timestamp('delivered_time')->nullable();
            $table->timestamp('cancelled_time')->nullable();
            $table->decimal('order_amount_myr', 12, 2)->default(0);
            $table->decimal('order_refund_amount_myr', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('fulfillment_type')->nullable();
            $table->string('product_category')->nullable();
            $table->foreignId('matched_live_session_id')->nullable()->constrained('live_sessions')->nullOnDelete();
            $table->json('raw_row_json')->nullable();
            $table->timestamps();

            $table->index('matched_live_session_id');
            $table->index(['cancelled_time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drop in reverse creation order so FKs don't block the drop.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_orders');
        Schema::dropIfExists('tiktok_live_reports');
        Schema::dropIfExists('tiktok_report_imports');
        Schema::dropIfExists('live_host_payroll_items');
        Schema::dropIfExists('live_host_payroll_runs');
        Schema::dropIfExists('live_session_gmv_adjustments');
    }
};
