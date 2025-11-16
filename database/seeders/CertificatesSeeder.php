<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CertificatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏆 Seeding certificates...');

        // Create certificate templates
        $this->command->info('Creating certificate templates...');

        $certificateNames = [
            'Quran Memorization Completion',
            'Arabic Proficiency',
            'Islamic Studies Excellence',
            'Tajweed Master Certificate',
            'Fiqh Scholar Certificate',
        ];

        $certificates = collect();
        foreach ($certificateNames as $name) {
            $certificate = \App\Models\Certificate::factory()->create([
                'name' => $name,
                'status' => 'active',
            ]);
            $certificates->push($certificate);
        }

        $this->command->info('✅ Created 5 certificate templates');

        // Issue certificates to graduated students
        $this->command->info('Issuing certificates to completed enrollments...');

        $completedEnrollments = \App\Models\Enrollment::where('academic_status', \App\AcademicStatus::COMPLETED)->get();

        $issuedCount = 0;
        foreach ($completedEnrollments as $enrollment) {
            // 80% chance of getting a certificate
            if (rand(1, 100) <= 80) {
                \App\Models\CertificateIssue::factory()->issued()->create([
                    'certificate_id' => $certificates->random()->id,
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                ]);
                $issuedCount++;
            }
        }

        $this->command->info("✅ Issued {$issuedCount} certificates");
        $this->command->info('✨ Certificates seeding completed!');
    }
}
