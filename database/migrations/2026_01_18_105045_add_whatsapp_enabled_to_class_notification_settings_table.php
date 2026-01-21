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
        Schema::table('class_notification_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false)->after('send_to_teacher');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_notification_settings', function (Blueprint $table) {
            $table->dropColumn('whatsapp_enabled');
        });
    }
};
