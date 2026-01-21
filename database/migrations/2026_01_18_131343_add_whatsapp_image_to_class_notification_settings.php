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
            // WhatsApp image path for custom templates
            $table->string('whatsapp_image_path')->nullable()->after('whatsapp_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_notification_settings', function (Blueprint $table) {
            $table->dropColumn('whatsapp_image_path');
        });
    }
};
