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
     * The unverify path (Task 11 of verify-link) clears gmv_source along with
     * the matched record + gmv_amount, so the column must accept NULL. Default
     * stays 'manual' for newly created sessions that haven't been verified yet.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE live_sessions MODIFY gmv_source VARCHAR(255) NULL DEFAULT 'manual'");

            return;
        }

        // SQLite: add new nullable column, copy data, drop old, rename new.
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->string('gmv_source_new')->nullable()->default('manual')->after('gmv_source');
        });

        DB::table('live_sessions')->update([
            'gmv_source_new' => DB::raw('gmv_source'),
        ]);

        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropColumn('gmv_source');
        });

        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->renameColumn('gmv_source_new', 'gmv_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        // Backfill any NULLs to the default before re-enforcing NOT NULL.
        DB::table('live_sessions')->whereNull('gmv_source')->update(['gmv_source' => 'manual']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE live_sessions MODIFY gmv_source VARCHAR(255) NOT NULL DEFAULT 'manual'");

            return;
        }

        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->string('gmv_source_new')->default('manual')->after('gmv_source');
        });

        DB::table('live_sessions')->update([
            'gmv_source_new' => DB::raw('gmv_source'),
        ]);

        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropColumn('gmv_source');
        });

        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->renameColumn('gmv_source_new', 'gmv_source');
        });
    }
};
