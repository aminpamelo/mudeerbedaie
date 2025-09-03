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
        // Create teacher user
        $teacherUser = User::create([
            'name' => 'John Teacher',
            'email' => 'teacher@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'teacher',
        ]);

        // Create teacher profile
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'teacher_id' => 'TID001',
            'ic_number' => '880101-14-5678',
            'phone' => '012-345-6789',
            'status' => 'active',
            'joined_at' => now(),
            'bank_account_holder' => 'John Teacher',
            'bank_account_number' => '1234567890',
            'bank_name' => 'Maybank',
        ]);

        // Create courses
        $course1 = Course::create([
            'teacher_id' => $teacher->id,
            'name' => 'Islamic Studies',
            'description' => 'Comprehensive Islamic Studies course covering Quran, Hadith, and Islamic jurisprudence.',
            'price' => 150.00,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $course2 = Course::create([
            'teacher_id' => $teacher->id,
            'name' => 'Arabic Language',
            'description' => 'Learn Arabic language from beginner to advanced level.',
            'price' => 120.00,
            'status' => 'active',
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

        // Create some students
        $students = [];
        for ($i = 1; $i <= 5; $i++) {
            $studentUser = User::create([
                'name' => "Student {$i}",
                'email' => "student{$i}@example.com",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'student',
            ]);

            $student = Student::create([
                'user_id' => $studentUser->id,
                'student_id' => 'STU'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'ic_number' => '90010'.$i.'-14-567'.$i,
                'phone' => '012-345-678'.$i,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);

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
