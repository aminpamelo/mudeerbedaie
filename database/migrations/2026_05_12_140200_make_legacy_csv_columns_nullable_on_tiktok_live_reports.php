<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relax CSV-era NOT NULL constraints (import_id, tiktok_creator_id, and
     * the metric/decimal columns) so API-sourced rows from
     * TikTokLiveSyncService — which legitimately omit import_id/creator id
     * and may report null for non-MYR currency conversions — can be inserted.
     *
     * Dual-driver: MySQL uses raw ALTER, SQLite rebuilds via rename + copy.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // import_id is a FK; drop+recreate the constraint to switch nullability.
            Schema::table('tiktok_live_reports', function (Blueprint $table) {
                $table->dropForeign(['import_id']);
            });
            DB::statement('ALTER TABLE tiktok_live_reports MODIFY import_id BIGINT UNSIGNED NULL');
            Schema::table('tiktok_live_reports', function (Blueprint $table) {
                $table->foreign('import_id')
                    ->references('id')->on('tiktok_report_imports')
                    ->cascadeOnDelete();
            });
            DB::statement('ALTER TABLE tiktok_live_reports MODIFY tiktok_creator_id VARCHAR(255) NULL');

            // Decimals: API may legitimately produce null (e.g. non-MYR currency).
            DB::statement('ALTER TABLE tiktok_live_reports MODIFY gmv_myr DECIMAL(12,2) NULL');
            DB::statement('ALTER TABLE tiktok_live_reports MODIFY live_attributed_gmv_myr DECIMAL(12,2) NULL');
            DB::statement('ALTER TABLE tiktok_live_reports MODIFY avg_price_myr DECIMAL(10,2) NULL');
            DB::statement('ALTER TABLE tiktok_live_reports MODIFY click_to_order_rate DECIMAL(6,2) NULL');
            DB::statement('ALTER TABLE tiktok_live_reports MODIFY ctr DECIMAL(6,2) NULL');

            // Counts: API endpoint may omit fields per-row.
            foreach ([
                'products_added', 'products_sold', 'sku_orders', 'items_sold',
                'unique_customers', 'viewers', 'views', 'avg_view_duration_sec',
                'comments', 'shares', 'likes', 'new_followers',
                'product_impressions', 'product_clicks',
            ] as $col) {
                DB::statement("ALTER TABLE tiktok_live_reports MODIFY {$col} INT NULL");
            }

            return;
        }

        // SQLite: rebuild table to relax NOT NULL on import_id, tiktok_creator_id.
        Schema::create('tiktok_live_reports_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->nullable()->constrained('tiktok_report_imports')->cascadeOnDelete();
            $table->string('tiktok_creator_id')->nullable()->index();
            $table->string('creator_nickname')->nullable();
            $table->string('creator_display_name')->nullable();
            $table->timestamp('launched_time')->index();
            $table->integer('duration_seconds')->nullable();
            $table->decimal('gmv_myr', 12, 2)->nullable();
            $table->decimal('live_attributed_gmv_myr', 12, 2)->nullable();
            $table->integer('products_added')->nullable();
            $table->integer('products_sold')->nullable();
            $table->integer('sku_orders')->nullable();
            $table->integer('items_sold')->nullable();
            $table->integer('unique_customers')->nullable();
            $table->decimal('avg_price_myr', 10, 2)->nullable();
            $table->decimal('click_to_order_rate', 6, 2)->nullable();
            $table->integer('viewers')->nullable();
            $table->integer('views')->nullable();
            $table->integer('avg_view_duration_sec')->nullable();
            $table->integer('comments')->nullable();
            $table->integer('shares')->nullable();
            $table->integer('likes')->nullable();
            $table->integer('new_followers')->nullable();
            $table->integer('product_impressions')->nullable();
            $table->integer('product_clicks')->nullable();
            $table->decimal('ctr', 6, 2)->nullable();
            $table->foreignId('matched_live_session_id')->nullable()->constrained('live_sessions')->nullOnDelete();
            $table->json('raw_row_json')->nullable();
            $table->timestamps();
            $table->string('tiktok_live_id')->nullable();
            $table->foreignId('platform_account_id')->nullable()
                ->constrained('platform_accounts')->nullOnDelete();
            $table->string('source', 16)->default('csv');
            $table->timestamp('synced_at')->nullable();

            // Temp index names to avoid collisions with the original table's
            // indexes (SQLite indexes are global, not table-scoped).
            $table->index('matched_live_session_id', 'tlr_new_matched_live_session_idx');
            $table->index('tiktok_creator_id', 'tlr_new_tiktok_creator_id_idx');
            $table->index('launched_time', 'tlr_new_launched_time_idx');
            $table->unique(['platform_account_id', 'tiktok_live_id'], 'tlr_new_account_live_unique');
        });

        DB::statement('INSERT INTO tiktok_live_reports_new SELECT * FROM tiktok_live_reports');
        Schema::drop('tiktok_live_reports');
        Schema::rename('tiktok_live_reports_new', 'tiktok_live_reports');

        // SQLite has no ALTER INDEX RENAME; drop temp indexes and recreate
        // them with canonical names so downstream code that references them
        // by name (e.g. dropUnique('tlr_account_live_unique')) keeps working.
        DB::statement('DROP INDEX IF EXISTS tlr_new_account_live_unique');
        DB::statement('DROP INDEX IF EXISTS tlr_new_matched_live_session_idx');
        DB::statement('DROP INDEX IF EXISTS tlr_new_tiktok_creator_id_idx');
        DB::statement('DROP INDEX IF EXISTS tlr_new_launched_time_idx');

        Schema::table('tiktok_live_reports', function (Blueprint $table) {
            $table->unique(['platform_account_id', 'tiktok_live_id'], 'tlr_account_live_unique');
            $table->index('matched_live_session_id');
            $table->index('tiktok_creator_id');
            $table->index('launched_time');
        });
    }

    public function down(): void
    {
        // Intentionally a no-op: reverting could fail if API-sourced rows
        // (which legitimately have null import_id) already exist.
    }
};
