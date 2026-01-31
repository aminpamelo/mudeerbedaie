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
        if (Schema::hasColumn('funnel_sessions', 'affiliate_id')) {
            return;
        }

        Schema::table('funnel_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('affiliate_id')->nullable()->after('student_id');
            $table->index('affiliate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_sessions', function (Blueprint $table) {
            $table->dropIndex(['affiliate_id']);
            $table->dropColumn('affiliate_id');
        });
    }
};
