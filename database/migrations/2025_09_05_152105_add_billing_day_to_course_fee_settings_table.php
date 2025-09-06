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
        Schema::table('course_fee_settings', function (Blueprint $table) {
            $table->integer('billing_day')->nullable()->after('billing_cycle')
                ->comment('Day of month to sync payment (1-31). Null means use default billing cycle start.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_fee_settings', function (Blueprint $table) {
            $table->dropColumn('billing_day');
        });
    }
};
