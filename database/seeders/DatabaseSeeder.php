<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting comprehensive database seeding...');
        $this->command->newLine();

        $this->call([
            // 1. Foundation: Users, Settings, Teachers
            AdminUserSeeder::class,
            AdditionalUsersSeeder::class,
            SettingsSeeder::class,
            TeacherSeeder::class,

            // 2. Courses and Classes Setup
            CoursesAndClassesSeeder::class,

            // 3. Students and Enrollments
            StudentsAndEnrollmentsSeeder::class,

            // 4. Sessions and Attendance
            ClassSessionsAndAttendanceSeeder::class,

            // 5. Products and Orders
            ProductsAndOrdersSeeder::class,

            // 5.5 Agents, Companies and Agent Orders
            AgentSeeder::class,

            // 6. Certificates
            CertificatesSeeder::class,

            // 7. Payroll
            PayrollSeeder::class,

            // 8. Broadcasting
            BroadcastingSeeder::class,

            // 9. Platforms and Integrations
            PlatformSeeder::class,
            LiveHostSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('✨ Database seeding completed successfully!');
        $this->command->newLine();

        // Display summary statistics
        $this->displaySummary();
    }

    protected function displaySummary(): void
    {
        $this->command->info('📊 Seeding Summary:');
        $this->command->table(
            ['Module', 'Count'],
            [
                ['Users', \App\Models\User::count()],
                ['Teachers', \App\Models\Teacher::count()],
                ['Students', \App\Models\Student::count()],
                ['Courses', \App\Models\Course::count()],
                ['Classes', \App\Models\ClassModel::count()],
                ['Enrollments', \App\Models\Enrollment::count()],
                ['Class Sessions', \App\Models\ClassSession::count()],
                ['Attendance Records', \App\Models\ClassAttendance::count()],
                ['Products', \App\Models\Product::count()],
                ['Product Orders', \App\Models\ProductOrder::count()],
                ['Agents', \App\Models\Agent::count()],
                ['Certificates', \App\Models\Certificate::count()],
                ['Certificate Issues', \App\Models\CertificateIssue::count()],
                ['Payslips', \App\Models\Payslip::count()],
                ['Audiences', \App\Models\Audience::count()],
                ['Broadcasts', \App\Models\Broadcast::count()],
            ]
        );
    }
}
