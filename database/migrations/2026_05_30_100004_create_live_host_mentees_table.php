<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_mentees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained('live_host_mentoring_programs')
                ->cascadeOnDelete();
            // The live_host User being mentored. Unlike recruitment applicants
            // (standalone until hired), a mentee is always an existing user.
            $table->foreignId('mentee_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            // Optional per-mentee mentor override; falls back to the program leader.
            $table->foreignId('mentor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('mentee_number')->unique(); // LHM-YYYYMM-0001
            $table->foreignId('current_stage_id')
                ->nullable()
                ->constrained('live_host_mentoring_stages')
                ->nullOnDelete();
            $table->string('status')->default('active'); // active|graduated|dropped
            $table->foreignId('level_id')
                ->nullable()
                ->constrained('live_host_mentoring_levels')
                ->nullOnDelete();
            $table->string('level_source')->nullable(); // auto|manual — how the level was last set
            $table->timestamp('level_assigned_at')->nullable();
            $table->foreignId('level_assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('enrolled_at');
            $table->timestamp('graduated_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'mentee_user_id']);
            $table->index(['program_id', 'status']);
            $table->index(['current_stage_id', 'status']);
            $table->index('mentee_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentees');
    }
};
