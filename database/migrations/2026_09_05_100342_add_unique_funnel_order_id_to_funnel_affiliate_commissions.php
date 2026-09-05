<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Collapse any pre-existing duplicate commissions (a symptom of the
        // callback↔return race) before adding the unique index, keeping the
        // earliest row per order. NULL funnel_order_id rows are left untouched
        // (a unique index permits multiple NULLs on both MySQL and SQLite).
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'DELETE c1 FROM funnel_affiliate_commissions c1 '.
                'INNER JOIN funnel_affiliate_commissions c2 '.
                'ON c1.funnel_order_id = c2.funnel_order_id AND c1.id > c2.id '.
                'WHERE c1.funnel_order_id IS NOT NULL'
            );
        } else {
            DB::statement(
                'DELETE FROM funnel_affiliate_commissions '.
                'WHERE funnel_order_id IS NOT NULL AND id NOT IN ('.
                'SELECT MIN(id) FROM funnel_affiliate_commissions '.
                'WHERE funnel_order_id IS NOT NULL GROUP BY funnel_order_id)'
            );
        }

        Schema::table('funnel_affiliate_commissions', function (Blueprint $table) {
            $table->unique('funnel_order_id', 'fac_funnel_order_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_affiliate_commissions', function (Blueprint $table) {
            $table->dropUnique('fac_funnel_order_id_unique');
        });
    }
};
