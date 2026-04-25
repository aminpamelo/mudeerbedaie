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
        Schema::create('session_replacement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_schedule_assignment_id')
                ->constrained('live_schedule_assignments')
                ->cascadeOnDelete();
            $table->foreignId('original_host_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('replacement_host_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('scope'); // 'one_date' | 'permanent'
            $table->date('target_date')->nullable();
            $table->string('reason_category'); // 'sick' | 'family' | 'personal' | 'other'
            $table->text('reason_note')->nullable();
            $table->string('status')->default('pending'); // 'pending'|'assigned'|'withdrawn'|'expired'|'rejected'
            $table->dateTime('requested_at');
            $table->dateTime('assigned_at')->nullable();
            $table->foreignId('assigned_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('expires_at');
            $table->foreignId('live_session_id')
                ->nullable()
                ->constrained('live_sessions')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
            $table->index(['original_host_id', 'requested_at']);
            $table->index(['live_schedule_assignment_id', 'target_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_replacement_requests');
    }
};
