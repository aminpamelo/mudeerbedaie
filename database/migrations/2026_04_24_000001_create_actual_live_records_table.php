<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actual_live_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')
                ->constrained('platform_accounts')
                ->cascadeOnDelete();

            $table->enum('source', ['csv_import', 'api_sync']);
            $table->string('source_record_id')->nullable();

            $table->foreignId('import_id')
                ->nullable()
                ->constrained('tiktok_report_imports')
                ->nullOnDelete();

            $table->string('creator_platform_user_id')->nullable();
            $table->string('creator_handle')->nullable();

            $table->timestamp('launched_time');
            $table->timestamp('ended_time')->nullable();
            $table->integer('duration_seconds')->nullable();

            $table->decimal('gmv_myr', 15, 2)->default(0);
            $table->decimal('live_attributed_gmv_myr', 15, 2)->default(0);

            $table->unsignedInteger('viewers')->nullable();
            $table->unsignedInteger('views')->nullable();
            $table->unsignedInteger('comments')->nullable();
            $table->unsignedInteger('shares')->nullable();
            $table->unsignedInteger('likes')->nullable();
            $table->unsignedInteger('new_followers')->nullable();

            $table->unsignedInteger('products_added')->nullable();
            $table->unsignedInteger('products_sold')->nullable();
            $table->unsignedInteger('items_sold')->nullable();
            $table->unsignedInteger('sku_orders')->nullable();

            $table->unsignedInteger('unique_customers')->nullable();
            $table->decimal('avg_price_myr', 15, 2)->nullable();
            $table->decimal('click_to_order_rate', 8, 4)->nullable();
            $table->decimal('ctr', 8, 4)->nullable();

            $table->json('raw_json')->nullable();

            $table->timestamps();

            $table->index(
                ['platform_account_id', 'creator_platform_user_id', 'launched_time'],
                'alr_candidate_idx'
            );
            $table->unique(['source', 'source_record_id'], 'alr_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actual_live_records');
    }
};
