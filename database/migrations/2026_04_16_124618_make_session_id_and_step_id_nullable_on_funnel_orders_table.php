<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes session_id and step_id nullable on funnel_orders to support
     * POS-originated upsell orders, which bypass visitor tracking and
     * therefore have no FunnelSession / FunnelStep context.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // MySQL: drop FK on session_id, change to nullable, restore FK
            Schema::table('funnel_orders', function (Blueprint $table) {
                $table->dropForeign(['session_id']);
            });

            DB::statement('ALTER TABLE funnel_orders MODIFY session_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE funnel_orders MODIFY step_id BIGINT UNSIGNED NULL');

            Schema::table('funnel_orders', function (Blueprint $table) {
                $table->foreign('session_id')->references('id')->on('funnel_sessions')->cascadeOnDelete();
            });
        } else {
            // SQLite: Laravel 12 supports native ALTER COLUMN for simple nullable changes on plain columns
            Schema::table('funnel_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('session_id')->nullable()->change();
                $table->unsignedBigInteger('step_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('funnel_orders', function (Blueprint $table) {
                $table->dropForeign(['session_id']);
            });

            DB::statement('ALTER TABLE funnel_orders MODIFY session_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE funnel_orders MODIFY step_id BIGINT UNSIGNED NOT NULL');

            Schema::table('funnel_orders', function (Blueprint $table) {
                $table->foreign('session_id')->references('id')->on('funnel_sessions')->cascadeOnDelete();
            });
        } else {
            Schema::table('funnel_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('session_id')->nullable(false)->change();
                $table->unsignedBigInteger('step_id')->nullable(false)->change();
            });
        }
    }
};
