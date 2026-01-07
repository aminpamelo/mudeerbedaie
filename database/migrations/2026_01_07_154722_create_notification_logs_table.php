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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_notification_id')->constrained('scheduled_notifications')->onDelete('cascade');
            $table->string('recipient_type'); // student, teacher
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel')->default('email'); // email, whatsapp, sms
            $table->string('destination'); // Email address or phone number
            $table->string('status')->default('pending'); // pending, sent, failed, bounced, delivered
            $table->string('message_id')->nullable(); // External provider message ID
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['scheduled_notification_id', 'status']);
            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['channel', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
