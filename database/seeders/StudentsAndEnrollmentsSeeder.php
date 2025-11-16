<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StudentsAndEnrollmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎓 Seeding students and enrollments...');

        // Get existing courses (should be created by CoursesAndClassesSeeder)
        $courses = \App\Models\Course::all();

        if ($courses->isEmpty()) {
            $this->command->warn('⚠️  No courses found. Creating some courses first...');
            $courses = \App\Models\Course::factory(5)->create();
        }

        // Create students with different statuses
        $this->command->info('Creating 50 students...');

        // 30 active students
        $activeStudents = \App\Models\Student::factory(30)->create([
            'status' => 'active',
        ]);

        // 10 inactive students
        $inactiveStudents = \App\Models\Student::factory(10)->create([
            'status' => 'inactive',
        ]);

        // 5 suspended students
        $suspendedStudents = \App\Models\Student::factory(5)->create([
            'status' => 'suspended',
        ]);

        // 5 graduated students
        $graduatedStudents = \App\Models\Student::factory(5)->create([
            'status' => 'graduated',
        ]);

        $this->command->info('✅ Created 50 students (30 active, 10 inactive, 5 suspended, 5 graduated)');

        // Create enrollments for active students
        $this->command->info('Creating enrollments for active students...');

        $enrollmentCount = 0;
        foreach ($activeStudents as $student) {
            // Each active student enrolls in 1-3 random courses
            $coursesToEnroll = $courses->random(rand(1, 3));

            foreach ($coursesToEnroll as $course) {
                $enrollment = \App\Models\Enrollment::factory()->create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'academic_status' => \App\AcademicStatus::ACTIVE,
                    'enrollment_date' => now()->subDays(rand(1, 90)),
                ]);

                // 70% chance of having active subscription
                if (rand(1, 100) <= 70) {
                    $enrollment->update([
                        'stripe_subscription_id' => 'sub_'.fake()->lexify('????????????????'),
                        'subscription_status' => 'active',
                        'payment_method_type' => fake()->randomElement(['automatic', 'manual']),
                    ]);
                }

                $enrollmentCount++;
            }
        }

        $this->command->info("✅ Created {$enrollmentCount} active enrollments");

        // Create some completed enrollments for graduated students
        $this->command->info('Creating completed enrollments for graduated students...');

        $completedCount = 0;
        foreach ($graduatedStudents as $student) {
            $coursesToComplete = $courses->random(rand(1, 2));

            foreach ($coursesToComplete as $course) {
                \App\Models\Enrollment::factory()->completed()->create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                ]);
                $completedCount++;
            }
        }

        $this->command->info("✅ Created {$completedCount} completed enrollments");

        // Create some withdrawn enrollments
        $this->command->info('Creating withdrawn enrollments...');

        $withdrawnCount = 0;
        foreach ($inactiveStudents->take(5) as $student) {
            \App\Models\Enrollment::factory()->withdrawn()->create([
                'student_id' => $student->id,
                'course_id' => $courses->random()->id,
            ]);
            $withdrawnCount++;
        }

        $this->command->info("✅ Created {$withdrawnCount} withdrawn enrollments");

        // Enroll students in classes
        $this->command->info('Enrolling students in classes...');

        $classes = \App\Models\ClassModel::all();
        $classEnrollmentCount = 0;

        if ($classes->isNotEmpty()) {
            foreach (\App\Models\Enrollment::where('academic_status', \App\AcademicStatus::ACTIVE)->get() as $enrollment) {
                // Get classes for this course
                $courseClasses = $classes->where('course_id', $enrollment->course_id);

                if ($courseClasses->isNotEmpty()) {
                    // Enroll in 1-2 random classes from the course
                    $classesToJoin = $courseClasses->random(min($courseClasses->count(), rand(1, 2)));

                    foreach ($classesToJoin as $class) {
                        if ($class->canAddStudent()) {
                            \App\Models\ClassStudent::factory()->create([
                                'class_id' => $class->id,
                                'student_id' => $enrollment->student_id,
                                'status' => 'active',
                                'enrolled_at' => $enrollment->enrollment_date,
                            ]);
                            $classEnrollmentCount++;
                        }
                    }
                }
            }

            $this->command->info("✅ Enrolled students in {$classEnrollmentCount} classes");
        } else {
            $this->command->warn('⚠️  No classes found to enroll students in');
        }

        $this->command->info('✨ Students and enrollments seeding completed!');
    }
}
