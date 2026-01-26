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
        Schema::create('funnel_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->string('visitor_id');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            // UTM tracking
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            // Device info
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('country_code', 2)->nullable();
            // Progress
            $table->unsignedBigInteger('entry_step_id')->nullable();
            $table->unsignedBigInteger('current_step_id')->nullable();
            $table->enum('status', ['active', 'converted', 'abandoned'])->default('active');
            // Timestamps
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('converted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['funnel_id', 'visitor_id']);
            $table->index(['status', 'last_activity_at']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_sessions');
    }
};
