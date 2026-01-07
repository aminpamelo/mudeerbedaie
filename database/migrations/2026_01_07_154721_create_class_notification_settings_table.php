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
        Schema::create('class_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->string('notification_type'); // session_reminder_24h, session_reminder_3h, session_reminder_1h, session_reminder_30m, session_reminder_custom, session_followup_immediate, session_followup_1h, session_followup_24h, class_started, class_completed, enrollment_welcome
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->integer('custom_minutes_before')->nullable(); // For custom timing
            $table->boolean('send_to_students')->default(true);
            $table->boolean('send_to_teacher')->default(true);
            $table->text('custom_subject')->nullable(); // Override subject
            $table->longText('custom_content')->nullable(); // Override content
            $table->timestamps();

            $table->unique(['class_id', 'notification_type']);
            $table->index(['class_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_notification_settings');
    }
};
