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
        if (Schema::hasTable('live_schedule_assignments')) {
            return;
        }

        Schema::create('live_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('time_slot_id')->constrained('live_time_slots')->onDelete('cascade');
            $table->foreignId('live_host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('day_of_week')->comment('0=Sunday, 1=Monday, ..., 6=Saturday');
            $table->date('schedule_date')->nullable()->comment('Specific date for one-time assignments');
            $table->text('remarks')->nullable();
            $table->enum('status', ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->boolean('is_template')->default(true)->comment('True for recurring weekly template, false for specific date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for common queries (with short names to avoid MySQL 64-char limit)
            $table->index(['platform_account_id', 'day_of_week'], 'lsa_platform_day_idx');
            $table->index(['platform_account_id', 'schedule_date'], 'lsa_platform_date_idx');
            $table->index(['live_host_id', 'day_of_week'], 'lsa_host_day_idx');
            $table->index(['live_host_id', 'schedule_date'], 'lsa_host_date_idx');
            $table->index(['is_template', 'day_of_week'], 'lsa_template_day_idx');

            // Unique constraint for template slots (one host per platform/day/slot)
            $table->unique(
                ['platform_account_id', 'time_slot_id', 'day_of_week', 'is_template'],
                'unique_template_slot'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_schedule_assignments');
    }
};
