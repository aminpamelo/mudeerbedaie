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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type'); // session_reminder, session_followup, class_update, enrollment_welcome, class_completed
            $table->string('channel')->default('email'); // email, whatsapp, sms
            $table->string('subject')->nullable();
            $table->longText('content');
            $table->string('language')->default('ms'); // 'ms' for Malay, 'en' for English
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('available_placeholders')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'channel', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
