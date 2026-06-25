<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales is now a Ringgit (RM) value, not a raw unit count, so it must keep
     * sen. Widen sales_quantity from unsignedInteger to decimal(12,2).
     *
     * MySQL: raw ALTER. SQLite: rename → add → copy → drop (it cannot modify a
     * column in place), per the project's dual-driver migration rule.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE live_host_mentee_monthly_scores MODIFY sales_quantity DECIMAL(12,2) NULL');

            return;
        }

        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->renameColumn('sales_quantity', 'sales_quantity_old');
        });
        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->decimal('sales_quantity', 12, 2)->nullable()->after('attitude_score');
        });
        DB::table('live_host_mentee_monthly_scores')->update([
            'sales_quantity' => DB::raw('sales_quantity_old'),
        ]);
        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->dropColumn('sales_quantity_old');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE live_host_mentee_monthly_scores MODIFY sales_quantity INT UNSIGNED NULL');

            return;
        }

        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->renameColumn('sales_quantity', 'sales_quantity_old');
        });
        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->unsignedInteger('sales_quantity')->nullable()->after('attitude_score');
        });
        DB::table('live_host_mentee_monthly_scores')->update([
            'sales_quantity' => DB::raw('sales_quantity_old'),
        ]);
        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->dropColumn('sales_quantity_old');
        });
    }
};
