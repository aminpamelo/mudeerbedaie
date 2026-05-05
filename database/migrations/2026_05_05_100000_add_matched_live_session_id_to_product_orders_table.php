<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('matched_live_session_id')->nullable()->after('platform_account_id');

            $table->foreign('matched_live_session_id')
                ->references('id')
                ->on('live_sessions')
                ->nullOnDelete();

            $table->index(['platform_account_id', 'matched_live_session_id'], 'po_account_session_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropForeign(['matched_live_session_id']);
            $table->dropIndex('po_account_session_idx');
            $table->dropColumn('matched_live_session_id');
        });
    }
};
