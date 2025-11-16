<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('💰 Seeding payroll data...');

        $teachers = \App\Models\Teacher::all();

        if ($teachers->isEmpty()) {
            $this->command->warn('⚠️  No teachers found. Skipping payroll seeding.');

            return;
        }

        $this->command->info("Found {$teachers->count()} teachers");

        $payslipCount = 0;

        foreach ($teachers as $teacher) {
            // Get completed sessions for this teacher
            $completedSessions = \App\Models\ClassSession::query()
                ->whereHas('class', function ($q) use ($teacher) {
                    $q->where('teacher_id', $teacher->id);
                })
                ->where('status', 'completed')
                ->whereNotNull('allowance_amount')
                ->get();

            if ($completedSessions->isEmpty()) {
                continue;
            }

            // Create 1-2 payslips for the teacher
            $numberOfPayslips = min(2, ceil($completedSessions->count() / 10));

            for ($i = 0; $i < $numberOfPayslips; $i++) {
                $periodStart = now()->subMonths($numberOfPayslips - $i);
                $periodEnd = (clone $periodStart)->endOfMonth();
                $month = $periodStart->format('Y-m');
                $year = (int) $periodStart->format('Y');

                // Get sessions in this period
                $periodSessions = $completedSessions->filter(function ($session) use ($periodStart, $periodEnd) {
                    return $session->completed_at >= $periodStart && $session->completed_at <= $periodEnd;
                });

                if ($periodSessions->isEmpty()) {
                    continue;
                }

                $totalAmount = $periodSessions->sum('allowance_amount');

                // 60% chance payslip is paid, 30% finalized, 10% draft
                $rand = rand(1, 100);
                if ($rand <= 60) {
                    $payslip = \App\Models\Payslip::factory()->paid()->create([
                        'teacher_id' => $teacher->id,
                        'month' => $month,
                        'year' => $year,
                        'total_sessions' => $periodSessions->count(),
                        'total_amount' => $totalAmount,
                    ]);
                } elseif ($rand <= 90) {
                    $payslip = \App\Models\Payslip::factory()->finalized()->create([
                        'teacher_id' => $teacher->id,
                        'month' => $month,
                        'year' => $year,
                        'total_sessions' => $periodSessions->count(),
                        'total_amount' => $totalAmount,
                    ]);
                } else {
                    $payslip = \App\Models\Payslip::factory()->draft()->create([
                        'teacher_id' => $teacher->id,
                        'month' => $month,
                        'year' => $year,
                        'total_sessions' => $periodSessions->count(),
                        'total_amount' => $totalAmount,
                    ]);
                }

                // Link sessions to payslip
                foreach ($periodSessions as $session) {
                    \App\Models\PayslipSession::factory()->create([
                        'payslip_id' => $payslip->id,
                        'session_id' => $session->id,
                        'amount' => $session->allowance_amount,
                    ]);
                }

                $payslipCount++;
            }
        }

        $this->command->info("✅ Created {$payslipCount} payslips");
        $this->command->info('✨ Payroll seeding completed!');
    }
}
