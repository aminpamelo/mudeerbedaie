<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_mentoring_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color')->nullable();
            $table->unsignedInteger('position');
            $table->boolean('is_top')->default(false);
            $table->text('description')->nullable();
            // Auto-suggest thresholds — a mentee qualifies for this level when
            // their monthly KPIs meet every non-null minimum below. Nullable so
            // the entry level can have no gate. Tunable by the PIC.
            $table->unsignedInteger('min_sessions')->nullable();
            $table->decimal('min_hours', 8, 2)->nullable();
            $table->decimal('min_gmv_myr', 12, 2)->nullable();
            $table->unsignedTinyInteger('min_attendance_pct')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('position');
        });

        // Seed a sensible, fully-editable default ladder so the catalog is
        // usable immediately after `php artisan migrate` (no migrate:fresh).
        // Idempotent: only seeds when the table is empty.
        if (DB::table('live_host_mentoring_levels')->count() === 0) {
            $now = now();
            DB::table('live_host_mentoring_levels')->insert([
                [
                    'name' => 'Rookie', 'slug' => 'rookie', 'color' => '#A3A3A3', 'position' => 1,
                    'is_top' => false, 'description' => 'Just enrolled — learning the ropes.',
                    'min_sessions' => null, 'min_hours' => null, 'min_gmv_myr' => null, 'min_attendance_pct' => null,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'name' => 'Rising', 'slug' => 'rising', 'color' => '#38BDF8', 'position' => 2,
                    'is_top' => false, 'description' => 'Building consistency on the schedule.',
                    'min_sessions' => 8, 'min_hours' => 12, 'min_gmv_myr' => 3000, 'min_attendance_pct' => 70,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'name' => 'Pro', 'slug' => 'pro', 'color' => '#34D399', 'position' => 3,
                    'is_top' => false, 'description' => 'Reliable performer driving real GMV.',
                    'min_sessions' => 16, 'min_hours' => 28, 'min_gmv_myr' => 10000, 'min_attendance_pct' => 80,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'name' => 'Elite', 'slug' => 'elite', 'color' => '#A78BFA', 'position' => 4,
                    'is_top' => false, 'description' => 'Top-tier numbers, ready to mentor others.',
                    'min_sessions' => 28, 'min_hours' => 50, 'min_gmv_myr' => 25000, 'min_attendance_pct' => 88,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'name' => 'Top Host', 'slug' => 'top-host', 'color' => '#F59E0B', 'position' => 5,
                    'is_top' => true, 'description' => 'Graduated — eligible to lead a mentoring program.',
                    'min_sessions' => 40, 'min_hours' => 70, 'min_gmv_myr' => 50000, 'min_attendance_pct' => 92,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentoring_levels');
    }
};
