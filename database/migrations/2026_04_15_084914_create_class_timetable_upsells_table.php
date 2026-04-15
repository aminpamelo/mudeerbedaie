<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_timetable_upsells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_timetable_id')->constrained('class_timetables')->cascadeOnDelete();
            $table->string('day_of_week');
            $table->string('time_slot');
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->foreignId('pic_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['class_timetable_id', 'day_of_week', 'time_slot'], 'timetable_upsell_slot_unique');
            $table->index('funnel_id');
            $table->index('pic_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_timetable_upsells');
    }
};
