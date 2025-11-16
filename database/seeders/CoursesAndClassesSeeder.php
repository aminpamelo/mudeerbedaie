<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CoursesAndClassesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📚 Seeding courses and classes...');

        // Get existing teachers
        $teachers = \App\Models\Teacher::all();

        if ($teachers->isEmpty()) {
            $this->command->warn('⚠️  No teachers found. Creating some teachers first...');
            $teachers = \App\Models\Teacher::factory(10)->create();
        }

        // Create courses with different statuses
        $this->command->info('Creating 15 courses...');

        $courseNames = [
            'Quran Memorization',
            'Arabic Language Fundamentals',
            'Islamic Studies',
            'Tajweed Mastery',
            'Fiqh and Jurisprudence',
            'Hadith Sciences',
            'Tafsir Al-Quran',
            'Islamic History',
            'Arabic Grammar (Nahw)',
            'Arabic Morphology (Sarf)',
            'Seerah of the Prophet',
            'Aqeedah and Theology',
            'Islamic Finance',
            'Quranic Recitation',
            'Islamic Ethics and Manners',
        ];

        $courses = collect();

        foreach ($courseNames as $name) {
            $course = \App\Models\Course::factory()->create([
                'name' => $name,
                'description' => fake()->paragraph(3),
                'status' => fake()->randomElement(['active', 'active', 'active', 'inactive', 'draft']), // 60% active
            ]);

            // Create course settings
            \App\Models\CourseFeeSettings::factory()->create([
                'course_id' => $course->id,
                'fee_amount' => fake()->randomElement([150, 200, 250, 300, 350]),
            ]);

            \App\Models\CourseClassSettings::factory()->create([
                'course_id' => $course->id,
                'billing_type' => fake()->randomElement(['per_session', 'per_month']),
                'price_per_session' => fake()->randomFloat(2, 30, 80),
                'price_per_month' => fake()->randomFloat(2, 200, 500),
                'sessions_per_month' => fake()->numberBetween(8, 16),
            ]);

            $courses->push($course);
        }

        $this->command->info('✅ Created 15 courses with fee and class settings');

        // Create classes for active courses
        $this->command->info('Creating classes for courses...');

        $classCount = 0;
        foreach ($courses->where('status', 'active') as $course) {
            // Each active course gets 2-4 classes
            $numberOfClasses = rand(2, 4);

            for ($i = 1; $i <= $numberOfClasses; $i++) {
                $teacher = $teachers->random();

                $class = \App\Models\ClassModel::factory()->create([
                    'course_id' => $course->id,
                    'teacher_id' => $teacher->id,
                    'title' => $course->name.' - Class '.$i,
                    'description' => 'Learn '.$course->name.' with experienced instructors.',
                    'date_time' => now()->addDays(rand(7, 60)),
                    'duration_minutes' => fake()->randomElement([60, 90, 120]),
                    'class_type' => fake()->randomElement(['group', 'individual']),
                    'max_capacity' => fake()->numberBetween(5, 25),
                    'teacher_rate' => fake()->randomFloat(2, 50, 150),
                    'rate_type' => 'per_session',
                    'commission_type' => 'percentage',
                    'commission_value' => fake()->randomElement([20, 25, 30, 35]),
                    'status' => fake()->randomElement(['active', 'active', 'draft']), // 67% active
                    'enable_document_shipment' => false,
                    'shipment_frequency' => null,
                    'shipment_start_date' => null,
                    'shipment_product_id' => null,
                    'shipment_warehouse_id' => null,
                    'shipment_quantity_per_student' => null,
                    'shipment_notes' => null,
                ]);

                $classCount++;
            }
        }

        $this->command->info("✅ Created {$classCount} classes");

        $this->command->info('✨ Courses and classes seeding completed!');
    }
}
