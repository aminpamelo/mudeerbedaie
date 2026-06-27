<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Switch admin OT adjustments from decimal hours to signed integer minutes
     * so they line up with the minute-based OT balance (claims use minutes too).
     */
    public function up(): void
    {
        Schema::table('overtime_adjustments', function (Blueprint $table) {
            // Signed: positive adds OT minutes, negative deducts them.
            $table->integer('minutes')->default(0)->after('employee_id');
        });

        DB::statement('UPDATE overtime_adjustments SET minutes = ROUND(hours * 60)');

        Schema::table('overtime_adjustments', function (Blueprint $table) {
            $table->dropColumn('hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overtime_adjustments', function (Blueprint $table) {
            $table->decimal('hours', 5, 1)->default(0)->after('employee_id');
        });

        DB::statement('UPDATE overtime_adjustments SET hours = minutes / 60.0');

        Schema::table('overtime_adjustments', function (Blueprint $table) {
            $table->dropColumn('minutes');
        });
    }
};
