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
        if (!Schema::hasTable('tiktok_finance_statements')) {
            Schema::create('tiktok_finance_statements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
                $table->string('tiktok_statement_id')->index();
                $table->string('statement_type')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('order_amount', 15, 2)->default(0);
                $table->decimal('commission_amount', 15, 2)->default(0);
                $table->decimal('shipping_fee', 15, 2)->default(0);
                $table->decimal('platform_fee', 15, 2)->default(0);
                $table->string('currency', 3)->nullable();
                $table->string('status')->nullable();
                $table->timestamp('statement_time')->nullable();
                $table->json('raw_response')->nullable();
                $table->timestamps();
                $table->unique(['platform_account_id', 'tiktok_statement_id'], 'tfs_account_statement_unique');
            });
        }

        if (!Schema::hasTable('tiktok_finance_transactions')) {
            Schema::create('tiktok_finance_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
                $table->foreignId('statement_id')->nullable()->constrained('tiktok_finance_statements')->nullOnDelete();
                $table->string('tiktok_order_id')->nullable()->index();
                $table->string('transaction_type')->nullable();
                $table->decimal('order_amount', 15, 2)->default(0);
                $table->decimal('seller_revenue', 15, 2)->default(0);
                $table->decimal('affiliate_commission', 15, 2)->default(0);
                $table->decimal('platform_commission', 15, 2)->default(0);
                $table->decimal('shipping_fee', 15, 2)->default(0);
                $table->timestamp('order_created_at')->nullable();
                $table->json('raw_response')->nullable();
                $table->timestamps();
                $table->index(['platform_account_id', 'tiktok_order_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_finance_transactions');
        Schema::dropIfExists('tiktok_finance_statements');
    }
};
