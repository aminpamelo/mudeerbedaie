<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_orders', function (Blueprint $table) {
            $table->foreignId('class_session_id')->nullable()->after('bumps_accepted')->constrained('class_sessions')->nullOnDelete();
            $table->index('class_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_orders', function (Blueprint $table) {
            $table->dropForeign(['class_session_id']);
            $table->dropIndex(['class_session_id']);
            $table->dropColumn('class_session_id');
        });
    }
};
