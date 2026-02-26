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
        // Add email_enabled field to class_notification_settings (skip if already exists)
        if (! Schema::hasColumn('class_notification_settings', 'email_enabled')) {
            Schema::table('class_notification_settings', function (Blueprint $table) {
                $table->boolean('email_enabled')->default(true)->after('is_enabled');
            });
        }

        // Add global channel settings to classes table
        Schema::table('classes', function (Blueprint $table) {
            $table->boolean('email_channel_enabled')->default(true)->after('auto_schedule_notifications');
            $table->boolean('whatsapp_channel_enabled')->default(true)->after('email_channel_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('class_notification_settings', 'email_enabled')) {
            Schema::table('class_notification_settings', function (Blueprint $table) {
                $table->dropColumn('email_enabled');
            });
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['email_channel_enabled', 'whatsapp_channel_enabled']);
        });
    }
};
