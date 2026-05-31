<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_mentoring_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained('live_host_mentoring_programs')
                ->cascadeOnDelete();
            // The top host who performed the activity (drives the activity indicator).
            $table->foreignId('leader_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Null mentee_id = a program-wide activity (e.g. a group meeting).
            $table->foreignId('mentee_id')
                ->nullable()
                ->constrained('live_host_mentees')
                ->cascadeOnDelete();
            $table->string('type'); // coaching|meeting|training|check_in|other
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['program_id', 'occurred_at']);
            $table->index(['leader_user_id', 'occurred_at']);
            $table->index(['mentee_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentoring_activities');
    }
};
