<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->foreignId('upsell_funnel_id')->nullable()->after('payout_status')->constrained('funnels')->nullOnDelete();
            $table->foreignId('upsell_pic_user_id')->nullable()->after('upsell_funnel_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropForeign(['upsell_funnel_id']);
            $table->dropForeign(['upsell_pic_user_id']);
            $table->dropColumn(['upsell_funnel_id', 'upsell_pic_user_id']);
        });
    }
};
