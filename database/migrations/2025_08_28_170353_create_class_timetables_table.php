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
        Schema::create('class_timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->json('weekly_schedule'); // Days and times: {monday: ['09:00', '14:00'], tuesday: [...]}
            $table->enum('recurrence_pattern', ['weekly', 'bi_weekly'])->default('weekly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('total_sessions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_timetables');
    }
};
