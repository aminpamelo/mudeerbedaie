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
        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->string('tracking_id')->nullable()->unique()->after('error_message');
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->integer('open_count')->default(0)->after('opened_at');
            $table->timestamp('clicked_at')->nullable()->after('open_count');
            $table->integer('click_count')->default(0)->after('clicked_at');

            $table->index('tracking_id');
            $table->index('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->dropIndex(['tracking_id']);
            $table->dropIndex(['opened_at']);
            $table->dropColumn(['tracking_id', 'opened_at', 'open_count', 'clicked_at', 'click_count']);
        });
    }
};
