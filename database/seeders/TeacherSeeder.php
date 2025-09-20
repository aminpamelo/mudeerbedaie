<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\ClassStudent;
use App\Models\ClassTimetable;
use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teacherNames = [
            'Ahmad Faiz bin Abdullah',
            'Fatimah binti Hassan',
            'Mohammad Rizal bin Karim',
            'Siti Aminah binti Omar',
            'Yusuf bin Ibrahim',
        ];

        $teachers = [];

        // Create 5 teachers
        foreach ($teacherNames as $index => $name) {
            $teacherUser = User::firstOrCreate(
                ['email' => $index === 0 ? 'teacher@example.com' : 'teacher'.($index + 1).'@example.com'],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'role' => 'teacher',
                ]
            );

            $teacher = Teacher::firstOrCreate(
                ['teacher_id' => 'TID'.str_pad($index + 1, 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $teacherUser->id,
                    'ic_number' => '8801'.str_pad($index + 1, 2, '0', STR_PAD_LEFT).'145670',
                    'phone' => '012-345-678'.($index + 1),
                    'status' => 'active',
                    'joined_at' => now(),
                    'bank_account_holder' => $name,
                    'bank_account_number' => '123456789'.($index + 1),
                    'bank_name' => $index % 2 === 0 ? 'Maybank' : 'CIMB Bank',
                ]
            );

            $teachers[] = $teacher;
        }

        // Use the first teacher for the main courses and classes
        $teacher = $teachers[0];

        // Create courses
        $course1 = Course::create([
            'teacher_id' => $teacher->id,
            'name' => 'Islamic Studies',
            'description' => 'Comprehensive Islamic Studies course covering Quran, Hadith, and Islamic jurisprudence.',
            'status' => 'active',
            'created_by' => $teacher->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $course2 = Course::create([
            'teacher_id' => $teacher->id,
            'name' => 'Arabic Language',
            'description' => 'Learn Arabic language from beginner to advanced level.',
            'status' => 'active',
            'created_by' => $teacher->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create classes
        $class1 = ClassModel::create([
            'course_id' => $course1->id,
            'teacher_id' => $teacher->id,
            'title' => 'Test Timetable Class',
            'description' => 'A test class with weekly timetable for Islamic Studies',
            'date_time' => now()->addDays(1)->setTime(9, 0),
            'duration_minutes' => 60,
            'class_type' => 'group',
            'max_capacity' => 10,
            'location' => 'Online',
            'meeting_url' => 'https://zoom.us/j/123456789',
            'teacher_rate' => 50.00,
            'rate_type' => 'per_class',
            'commission_type' => 'percentage',
            'commission_value' => 20.00,
            'status' => 'active',
            'notes' => 'Test class for calendar functionality',
        ]);

        $class2 = ClassModel::create([
            'course_id' => $course2->id,
            'teacher_id' => $teacher->id,
            'title' => 'Arabic Language Basics',
            'description' => 'Basic Arabic language class',
            'date_time' => now()->addDays(2)->setTime(14, 0),
            'duration_minutes' => 90,
            'class_type' => 'group',
            'max_capacity' => 8,
            'location' => 'Online',
            'meeting_url' => 'https://zoom.us/j/987654321',
            'teacher_rate' => 75.00,
            'rate_type' => 'per_class',
            'commission_type' => 'fixed',
            'commission_value' => 30.00,
            'status' => 'active',
        ]);

        // Create timetables
        ClassTimetable::create([
            'class_id' => $class1->id,
            'weekly_schedule' => [
                'monday' => ['09:00', '14:00'],
                'wednesday' => ['09:00'],
                'friday' => ['10:00'],
            ],
            'recurrence_pattern' => 'weekly',
            'start_date' => now()->startOfWeek(),
            'end_date' => now()->addMonth(),
            'total_sessions' => 12,
            'is_active' => true,
        ]);

        ClassTimetable::create([
            'class_id' => $class2->id,
            'weekly_schedule' => [
                'tuesday' => ['14:00'],
                'thursday' => ['16:00'],
                'saturday' => ['11:00'],
            ],
            'recurrence_pattern' => 'weekly',
            'start_date' => now()->startOfWeek(),
            'end_date' => now()->addMonth(),
            'total_sessions' => 10,
            'is_active' => true,
        ]);

        // Create students with diverse names
        $studentNames = [
            'Aiman bin Rashid', 'Nur Aisyah binti Ahmad', 'Haziq bin Zulkifli', 'Siti Nurhaliza binti Yaacob',
            'Amin bin Hakim', 'Fatimah Zahra binti Mohamed', 'Luqman bin Ismail', 'Khadijah binti Sulaiman',
            'Hakim bin Rosli', 'Zainab binti Hashim', 'Irfan bin Mansor', 'Maryam binti Razak',
            'Faisal bin Daud', 'Aminah binti Zakaria', 'Safwan bin Khalid', 'Hafsah binti Nasir',
            'Zikri bin Amir', 'Ruqayyah binti Hasan', 'Ilham bin Aziz', 'Khadijahtul Kubra binti Rahman',
        ];

        $students = [];
        for ($i = 1; $i <= 20; $i++) {
            $studentName = $studentNames[$i - 1];

            $studentUser = User::firstOrCreate(
                ['email' => "student{$i}@example.com"],
                [
                    'name' => $studentName,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'role' => 'student',
                ]
            );

            $student = Student::firstOrCreate(
                ['student_id' => 'STU'.str_pad($i, 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $studentUser->id,
                    'ic_number' => '9001'.str_pad($i, 2, '0', STR_PAD_LEFT).'145670',
                    'phone' => '012-345-'.str_pad(6780 + $i, 4, '0', STR_PAD_LEFT),
                    'status' => 'active',
                ]
            );

            $students[] = $student;
        }

        // Enroll students in classes
        foreach ($students as $student) {
            ClassStudent::create([
                'class_id' => $class1->id,
                'student_id' => $student->id,
                'enrolled_at' => now(),
                'status' => 'active',
            ]);

            if ($student->id % 2 === 0) { // Enroll every second student in class 2
                ClassStudent::create([
                    'class_id' => $class2->id,
                    'student_id' => $student->id,
                    'enrolled_at' => now(),
                    'status' => 'active',
                ]);
            }
        }

        // Create some scheduled sessions for today and this week
        $today = now();
        $weekStart = now()->startOfWeek();

        // Today's sessions
        ClassSession::create([
            'class_id' => $class1->id,
            'session_date' => $today->toDateString(),
            'session_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);

        // Tomorrow's sessions
        ClassSession::create([
            'class_id' => $class1->id,
            'session_date' => $today->copy()->addDay()->toDateString(),
            'session_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);

        ClassSession::create([
            'class_id' => $class2->id,
            'session_date' => $today->copy()->addDay()->toDateString(),
            'session_time' => '14:00:00',
            'duration_minutes' => 90,
            'status' => 'scheduled',
        ]);

        // Create a completed session from yesterday
        ClassSession::create([
            'class_id' => $class1->id,
            'session_date' => $today->copy()->subDay()->toDateString(),
            'session_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'completed',
            'completed_at' => $today->copy()->subDay()->setTime(10, 0),
            'allowance_amount' => 50.00,
        ]);

        $this->command->info('Teacher seeder completed successfully!');
    }
}
