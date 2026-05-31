<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-mentee, per-month performance evaluation (a 0–100 score the top host /
     * PIC records each month). One row per mentee per calendar month.
     */
    public function up(): void
    {
        Schema::create('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')
                ->constrained('live_host_mentees')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->unsignedTinyInteger('score')->nullable(); // 0-100
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['mentee_id', 'year', 'month']);
            $table->index(['mentee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentee_monthly_scores');
    }
};
