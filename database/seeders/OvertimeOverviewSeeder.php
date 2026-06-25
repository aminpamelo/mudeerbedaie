<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Populates the HR Overtime Overview with realistic data across the last six
 * months (including the current month, so the period cards aren't empty): a
 * status mix per month, completed records carrying actual + replacement hours
 * to feed the totals/trend/by-department widgets, and some in-flight requests
 * for the current period. Additive — it does not touch existing records.
 */
class OvertimeOverviewSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::whereNotNull('department_id')->inRandomOrder()->limit(18)->get();

        if ($employees->isEmpty()) {
            $this->command?->warn('No employees with a department found — skipping overtime seed.');

            return;
        }

        $approver = User::where('role', 'admin')->value('id');
        $now = Carbon::now();

        $reasons = [
            'Month-end closing support',
            'Urgent client deliverable',
            'System migration after hours',
            'Stock take and inventory audit',
            'Live event coverage',
            'Backlog clearance',
            'Cover for an absent colleague',
            'Campaign launch preparation',
            'Customer escalation handling',
        ];

        $created = 0;

        // Last 6 months including the current one.
        for ($m = 5; $m >= 0; $m--) {
            $month = $now->copy()->subMonths($m);
            $isCurrentMonth = $m === 0;

            foreach ($employees as $employee) {
                // Not everyone works overtime every month.
                if (! fake()->boolean($isCurrentMonth ? 55 : 65)) {
                    continue;
                }

                foreach (range(1, fake()->numberBetween(1, 2)) as $ignored) {
                    $maxDay = $isCurrentMonth ? min($now->day, $month->daysInMonth) : $month->daysInMonth;
                    $date = $month->copy()->day(fake()->numberBetween(1, max(1, $maxDay)));

                    $status = $this->pickStatus($isCurrentMonth);
                    $estimated = fake()->randomElement([1.5, 2.0, 2.5, 3.0, 3.5, 4.0]);
                    $start = Carbon::createFromTime(18, 0);
                    $end = $start->copy()->addMinutes((int) round($estimated * 60));

                    $data = [
                        'employee_id' => $employee->id,
                        'requested_date' => $date->toDateString(),
                        'start_time' => $start->format('H:i'),
                        'end_time' => $end->format('H:i'),
                        'estimated_hours' => $estimated,
                        'reason' => fake()->randomElement($reasons),
                        'status' => $status,
                    ];

                    if (in_array($status, ['approved', 'completed', 'rejected'], true)) {
                        $data['approved_by'] = $approver;
                        $data['approved_at'] = $date->copy()->setTime(9, fake()->numberBetween(0, 59));
                    }

                    if ($status === 'completed') {
                        $actual = max(0.5, $estimated + fake()->randomElement([-0.5, 0, 0, 0.5]));
                        $data['actual_hours'] = $actual;
                        $data['replacement_hours_earned'] = $actual;
                    }

                    if ($status === 'rejected') {
                        $data['rejection_reason'] = fake()->randomElement([
                            'Not pre-approved', 'Within normal capacity', 'Budget constraints',
                        ]);
                    }

                    OvertimeRequest::create($data);
                    $created++;
                }
            }
        }

        $this->command?->info("Seeded {$created} overtime requests across 6 months for {$employees->count()} employees.");
    }

    private function pickStatus(bool $isCurrentMonth): string
    {
        if ($isCurrentMonth) {
            // Current month: mostly in-flight, with a few resolved already.
            return fake()->randomElement([
                'pending', 'pending', 'pending',
                'approved', 'approved',
                'completed', 'completed',
                'cancelled',
            ]);
        }

        // Past months: mostly resolved (drives totals, trend, by-department).
        return fake()->randomElement([
            'completed', 'completed', 'completed', 'completed', 'completed',
            'approved',
            'rejected', 'rejected',
            'cancelled',
        ]);
    }
}
