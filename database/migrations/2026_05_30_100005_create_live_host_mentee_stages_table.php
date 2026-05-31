<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_mentee_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')
                ->constrained('live_host_mentees')
                ->cascadeOnDelete();
            $table->foreignId('stage_id')
                ->nullable()
                ->constrained('live_host_mentoring_stages')
                ->nullOnDelete();
            // The mentor responsible while the mentee sits in this stage.
            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->text('stage_notes')->nullable();
            $table->dateTime('entered_at');
            $table->dateTime('exited_at')->nullable();
            $table->timestamps();

            $table->index(['mentee_id', 'exited_at']);
            $table->index(['assignee_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentee_stages');
    }
};
