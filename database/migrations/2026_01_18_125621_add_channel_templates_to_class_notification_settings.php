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
            // Dedicated WhatsApp template field
            $table->longText('whatsapp_content')->nullable()->after('whatsapp_enabled');

            // SMS template field (for future use)
            $table->text('sms_content')->nullable()->after('whatsapp_content');

            // Flag to indicate if custom WhatsApp template is being used
            // vs falling back to converting email content
            $table->boolean('use_custom_whatsapp_template')->default(false)->after('sms_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_content',
                'sms_content',
                'use_custom_whatsapp_template',
            ]);
        });
    }
};
