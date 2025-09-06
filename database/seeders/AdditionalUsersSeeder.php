<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdditionalUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Additional teacher names
        $additionalTeachers = [
            'Ustaz Ahmad Syahir bin Mahmud',
            'Ustazah Noraini binti Sulaiman',
            'Ustaz Hafiz bin Abdul Rahman',
            'Ustazah Zuraida binti Mohamed',
        ];

        // Find the highest teacher number from existing emails
        $maxTeacherNumber = User::where('email', 'like', 'teacher%@example.com')
            ->get()
            ->map(function ($user) {
                preg_match('/teacher(\d+)@example\.com/', $user->email, $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        // Create additional teachers
        foreach ($additionalTeachers as $index => $name) {
            $teacherNumber = $maxTeacherNumber + $index + 1;

            $teacherUser = User::create([
                'name' => $name,
                'email' => 'teacher'.$teacherNumber.'@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'teacher',
            ]);

            // Generate unique teacher_id
            do {
                $teacherIdNumber = rand(100, 999);
                $teacherId = 'TID'.str_pad($teacherIdNumber, 3, '0', STR_PAD_LEFT);
            } while (Teacher::where('teacher_id', $teacherId)->exists());

            Teacher::create([
                'user_id' => $teacherUser->id,
                'teacher_id' => $teacherId,
                'ic_number' => '8701'.str_pad($index + 1, 2, '0', STR_PAD_LEFT).'145670'.($index % 10),
                'phone' => '013-456-789'.($index + 1),
                'status' => 'active',
                'joined_at' => now(),
                'bank_account_holder' => $name,
                'bank_account_number' => '987654321'.($index + 1),
                'bank_name' => $index % 3 === 0 ? 'Public Bank' : ($index % 2 === 0 ? 'RHB Bank' : 'Hong Leong Bank'),
            ]);
        }

        // Additional student names
        $additionalStudents = [
            'Arif bin Hakim', 'Nurul Huda binti Ismail', 'Danish bin Azman', 'Aisyah binti Karim',
            'Faris bin Zainal', 'Sofiah binti Rashid', 'Iqbal bin Yusuf', 'Nadia binti Hassan',
            'Rayyan bin Omar', 'Yasmin binti Khalid', 'Syafiq bin Razak', 'Nabila binti Mansor',
            'Afiq bin Daud', 'Husna binti Aziz', 'Zharif bin Nasir', 'Aliya binti Rahman',
            'Fahim bin Amir', 'Wardina binti Hasan', 'Zidan bin Hashim',
        ];

        // Find the highest student number from existing emails
        $maxStudentNumber = User::where('email', 'like', 'student%@example.com')
            ->get()
            ->map(function ($user) {
                preg_match('/student(\d+)@example\.com/', $user->email, $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        // Create additional students
        foreach ($additionalStudents as $index => $name) {
            $studentNumber = $maxStudentNumber + $index + 1;

            $studentUser = User::create([
                'name' => $name,
                'email' => 'student'.$studentNumber.'@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'student',
            ]);

            // Generate unique student_id
            do {
                $studentIdNumber = rand(10000000, 99999999);
                $studentId = 'STU'.$studentIdNumber;
            } while (Student::where('student_id', $studentId)->exists());

            Student::create([
                'user_id' => $studentUser->id,
                'student_id' => $studentId,
                'ic_number' => '9101'.str_pad($index + 1, 2, '0', STR_PAD_LEFT).'14567'.($index % 10),
                'phone' => '014-567-'.str_pad(8900 + $index, 4, '0', STR_PAD_LEFT),
                'status' => 'active',
            ]);
        }

        $this->command->info('Additional users seeder completed! Added '.count($additionalTeachers).' teachers and '.count($additionalStudents).' students.');
    }
}
