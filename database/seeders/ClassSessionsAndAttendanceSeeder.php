<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ClassSessionsAndAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📅 Seeding class sessions and attendance...');

        $classes = \App\Models\ClassModel::where('status', 'active')->get();

        if ($classes->isEmpty()) {
            $this->command->warn('⚠️  No active classes found. Skipping session seeding.');

            return;
        }

        $this->command->info("Found {$classes->count()} active classes");

        $sessionCount = 0;
        $attendanceCount = 0;

        foreach ($classes as $class) {
            // Create 5-10 sessions per class (mix of past, present, and future)
            $numberOfSessions = rand(5, 10);

            for ($i = 0; $i < $numberOfSessions; $i++) {
                $sessionDate = now()->subDays(rand(-30, 60)); // -30 to 60 days (past and future)
                $isPast = $sessionDate->isPast();

                // Determine session status based on date
                if ($isPast) {
                    // Past sessions: 70% completed, 20% cancelled, 10% no-show
                    $rand = rand(1, 100);
                    if ($rand <= 70) {
                        $session = \App\Models\ClassSession::factory()->completed()->create([
                            'class_id' => $class->id,
                            'session_date' => $sessionDate,
                            'duration_minutes' => $class->duration_minutes,
                        ]);
                    } elseif ($rand <= 90) {
                        $session = \App\Models\ClassSession::factory()->cancelled()->create([
                            'class_id' => $class->id,
                            'session_date' => $sessionDate,
                            'duration_minutes' => $class->duration_minutes,
                        ]);
                    } else {
                        $session = \App\Models\ClassSession::factory()->noShow()->create([
                            'class_id' => $class->id,
                            'session_date' => $sessionDate,
                            'duration_minutes' => $class->duration_minutes,
                        ]);
                    }

                    // Create attendance for completed sessions
                    if ($session->status === 'completed') {
                        $students = $class->classStudents()->where('status', 'active')->get();

                        foreach ($students as $classStudent) {
                            // 85% present, 10% absent, 5% late
                            $rand = rand(1, 100);
                            if ($rand <= 85) {
                                \App\Models\ClassAttendance::factory()->present()->create([
                                    'session_id' => $session->id,
                                    'student_id' => $classStudent->student_id,
                                ]);
                            } elseif ($rand <= 95) {
                                \App\Models\ClassAttendance::factory()->absent()->create([
                                    'session_id' => $session->id,
                                    'student_id' => $classStudent->student_id,
                                ]);
                            } else {
                                \App\Models\ClassAttendance::factory()->late()->create([
                                    'session_id' => $session->id,
                                    'student_id' => $classStudent->student_id,
                                ]);
                            }
                            $attendanceCount++;
                        }
                    }
                } else {
                    // Future sessions: all scheduled
                    $session = \App\Models\ClassSession::factory()->scheduled()->create([
                        'class_id' => $class->id,
                        'session_date' => $sessionDate,
                        'duration_minutes' => $class->duration_minutes,
                    ]);
                }

                $sessionCount++;
            }
        }

        $this->command->info("✅ Created {$sessionCount} sessions");
        $this->command->info("✅ Created {$attendanceCount} attendance records");
        $this->command->info('✨ Class sessions and attendance seeding completed!');
    }
}
