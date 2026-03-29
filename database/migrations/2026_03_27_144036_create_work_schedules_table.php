<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_schedules')) {
            return;
        }

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['fixed', 'flexible', 'shift']);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('break_duration_minutes')->default(60);
            $table->decimal('min_hours_per_day', 4, 1)->default(8.0);
            $table->integer('grace_period_minutes')->default(10);
            $table->json('working_days')->default('[1,2,3,4,5]');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
