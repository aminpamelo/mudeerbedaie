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
        if (Schema::hasTable('meetings')) {
            return;
        }

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_series_id')->nullable()->constrained('meeting_series')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('meeting_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('organizer_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('note_taker_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
