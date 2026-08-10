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
        Schema::create('tms_badges', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->default('trophy');
            $table->string('color', 7)->default('#f59e0b');
            $table->string('criteria_type');
            $table->unsignedInteger('criteria_value')->default(1);
            $table->unsignedInteger('points')->default(10);
            $table->timestamps();
        });

        Schema::create('tms_badge_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('badge_id')->constrained('tms_badges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamps();
            $table->unique(['badge_id', 'user_id'], 'tms_ba_badge_user_unique');
        });

        Schema::create('tms_user_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('tasks_created')->default(0);
            $table->unsignedInteger('tasks_overdue')->default(0);
            $table->unsignedInteger('time_tracked_seconds')->default(0);
            $table->unsignedInteger('streak_days')->default(0);
            $table->unsignedInteger('total_points')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'date'], 'tms_us_user_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tms_user_stats');
        Schema::dropIfExists('tms_badge_awards');
        Schema::dropIfExists('tms_badges');
    }
};
