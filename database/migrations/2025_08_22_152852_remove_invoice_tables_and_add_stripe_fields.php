<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove invoice-related tables (payments table references invoices, so drop it first)
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');

        // Add Stripe fields to courses table
        Schema::table('courses', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->unique()->after('status');
            $table->enum('stripe_sync_status', ['pending', 'synced', 'failed'])->default('pending')->after('stripe_product_id');
            $table->timestamp('stripe_last_synced_at')->nullable()->after('stripe_sync_status');
        });

        // Add Stripe fields to course_fee_settings table
        Schema::table('course_fee_settings', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->unique()->after('is_recurring');
            $table->integer('trial_period_days')->default(0)->after('stripe_price_id');
            $table->decimal('setup_fee', 8, 2)->default(0)->after('trial_period_days');
        });

        // Add Stripe fields to enrollments table
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->unique()->after('notes');
            $table->enum('subscription_status', [
                'incomplete',
                'incomplete_expired',
                'trialing',
                'active',
                'past_due',
                'canceled',
                'unpaid',
            ])->nullable()->after('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Stripe fields from enrollments table
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['stripe_subscription_id', 'subscription_status']);
        });

        // Remove Stripe fields from course_fee_settings table
        Schema::table('course_fee_settings', function (Blueprint $table) {
            $table->dropColumn(['stripe_price_id', 'trial_period_days', 'setup_fee']);
        });

        // Remove Stripe fields from courses table
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['stripe_product_id', 'stripe_sync_status', 'stripe_last_synced_at']);
        });

        // Recreate invoices table (basic structure for rollback)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('enrollment_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->string('billing_month');
            $table->decimal('amount', 8, 2);
            $table->date('due_date');
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->date('issued_date');
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_overdue_notification_sent_at')->nullable();
            $table->timestamps();
        });

        // Recreate invoice_items table (basic structure for rollback)
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 8, 2);
            $table->decimal('total_price', 8, 2);
            $table->timestamps();
        });

        // Recreate payments table (basic structure for rollback)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'cancelled', 'refunded', 'partially_refunded', 'requires_action', 'requires_payment_method'])->default('pending');
            $table->enum('type', ['stripe_card', 'bank_transfer']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MYR');
            $table->decimal('stripe_fee', 8, 2)->nullable();
            $table->decimal('net_amount', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->json('stripe_metadata')->nullable();
            $table->json('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_url')->nullable();
            $table->timestamps();
        });
    }
};
