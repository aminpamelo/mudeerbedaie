<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HrPhase2Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedLeaveTypes();
            $this->seedLeaveEntitlements();
            $this->seedWorkSchedules();
            $this->seedHolidays();
            $this->assignDefaultSchedules();
            $this->seedDepartmentApprovers();
            $this->initializeLeaveBalances();
            $this->seedAttendanceLogs();
            $this->seedOvertimeRequests();
            $this->seedLeaveRequests();
        });
    }

    /**
     * Seed default Malaysian leave types.
     */
    private function seedLeaveTypes(): void
    {
        $this->command->info('Seeding leave types...');

        $leaveTypes = [
            ['name' => 'Annual Leave', 'code' => 'AL', 'is_paid' => true, 'is_attachment_required' => false, 'is_system' => true, 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'Medical Leave', 'code' => 'MC', 'is_paid' => true, 'is_attachment_required' => true, 'is_system' => true, 'color' => '#EF4444', 'sort_order' => 2, 'max_consecutive_days' => 2],
            ['name' => 'Hospitalization Leave', 'code' => 'HL', 'is_paid' => true, 'is_attachment_required' => true, 'is_system' => true, 'color' => '#F97316', 'sort_order' => 3],
            ['name' => 'Maternity Leave', 'code' => 'ML', 'is_paid' => true, 'is_attachment_required' => true, 'is_system' => true, 'gender_restriction' => 'female', 'color' => '#EC4899', 'sort_order' => 4],
            ['name' => 'Paternity Leave', 'code' => 'PL', 'is_paid' => true, 'is_attachment_required' => false, 'is_system' => true, 'gender_restriction' => 'male', 'color' => '#8B5CF6', 'sort_order' => 5],
            ['name' => 'Compassionate Leave', 'code' => 'CL', 'is_paid' => true, 'is_attachment_required' => false, 'is_system' => true, 'color' => '#6B7280', 'sort_order' => 6],
            ['name' => 'Replacement Leave', 'code' => 'RL', 'is_paid' => true, 'is_attachment_required' => false, 'is_system' => true, 'color' => '#14B8A6', 'sort_order' => 7],
            ['name' => 'Unpaid Leave', 'code' => 'UL', 'is_paid' => false, 'is_attachment_required' => false, 'is_system' => true, 'color' => '#9CA3AF', 'sort_order' => 8],
        ];

        foreach ($leaveTypes as $data) {
            LeaveType::query()->firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command->info('  Created '.count($leaveTypes).' leave types.');
    }

    /**
     * Seed leave entitlement rules per Malaysian Employment Act 1955.
     */
    private function seedLeaveEntitlements(): void
    {
        $this->command->info('Seeding leave entitlements...');

        $al = LeaveType::query()->where('code', 'AL')->first();
        $mc = LeaveType::query()->where('code', 'MC')->first();
        $hl = LeaveType::query()->where('code', 'HL')->first();
        $ml = LeaveType::query()->where('code', 'ML')->first();
        $pl = LeaveType::query()->where('code', 'PL')->first();
        $cl = LeaveType::query()->where('code', 'CL')->first();
        $ul = LeaveType::query()->where('code', 'UL')->first();

        $entitlements = [
            // Annual Leave (AL) - by employment type and service years
            ['leave_type_id' => $al->id, 'employment_type' => 'full_time', 'min_service_months' => 0, 'max_service_months' => 23, 'days_per_year' => 8.0, 'is_prorated' => true, 'carry_forward_max' => 5],
            ['leave_type_id' => $al->id, 'employment_type' => 'full_time', 'min_service_months' => 24, 'max_service_months' => 59, 'days_per_year' => 12.0, 'is_prorated' => false, 'carry_forward_max' => 5],
            ['leave_type_id' => $al->id, 'employment_type' => 'full_time', 'min_service_months' => 60, 'max_service_months' => null, 'days_per_year' => 16.0, 'is_prorated' => false, 'carry_forward_max' => 5],
            ['leave_type_id' => $al->id, 'employment_type' => 'part_time', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 4.0, 'is_prorated' => true, 'carry_forward_max' => 0],
            ['leave_type_id' => $al->id, 'employment_type' => 'contract', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 8.0, 'is_prorated' => true, 'carry_forward_max' => 0],
            ['leave_type_id' => $al->id, 'employment_type' => 'intern', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 0.0, 'is_prorated' => false, 'carry_forward_max' => 0],

            // Medical Leave (MC) - by employment type and service years
            ['leave_type_id' => $mc->id, 'employment_type' => 'full_time', 'min_service_months' => 0, 'max_service_months' => 23, 'days_per_year' => 14.0, 'is_prorated' => false, 'carry_forward_max' => 0],
            ['leave_type_id' => $mc->id, 'employment_type' => 'full_time', 'min_service_months' => 24, 'max_service_months' => 59, 'days_per_year' => 18.0, 'is_prorated' => false, 'carry_forward_max' => 0],
            ['leave_type_id' => $mc->id, 'employment_type' => 'full_time', 'min_service_months' => 60, 'max_service_months' => null, 'days_per_year' => 22.0, 'is_prorated' => false, 'carry_forward_max' => 0],
            ['leave_type_id' => $mc->id, 'employment_type' => 'part_time', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 7.0, 'is_prorated' => false, 'carry_forward_max' => 0],
            ['leave_type_id' => $mc->id, 'employment_type' => 'contract', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 14.0, 'is_prorated' => false, 'carry_forward_max' => 0],
            ['leave_type_id' => $mc->id, 'employment_type' => 'intern', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 7.0, 'is_prorated' => false, 'carry_forward_max' => 0],

            // Hospitalization Leave (HL) - universal
            ['leave_type_id' => $hl->id, 'employment_type' => 'all', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 60.0, 'is_prorated' => false, 'carry_forward_max' => 0],

            // Maternity Leave (ML) - universal
            ['leave_type_id' => $ml->id, 'employment_type' => 'all', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 98.0, 'is_prorated' => false, 'carry_forward_max' => 0],

            // Paternity Leave (PL) - universal
            ['leave_type_id' => $pl->id, 'employment_type' => 'all', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 7.0, 'is_prorated' => false, 'carry_forward_max' => 0],

            // Compassionate Leave (CL) - universal
            ['leave_type_id' => $cl->id, 'employment_type' => 'all', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 3.0, 'is_prorated' => false, 'carry_forward_max' => 0],

            // Unpaid Leave (UL) - universal
            ['leave_type_id' => $ul->id, 'employment_type' => 'all', 'min_service_months' => 0, 'max_service_months' => null, 'days_per_year' => 365.0, 'is_prorated' => false, 'carry_forward_max' => 0],
        ];

        foreach ($entitlements as $data) {
            LeaveEntitlement::query()->firstOrCreate(
                [
                    'leave_type_id' => $data['leave_type_id'],
                    'employment_type' => $data['employment_type'],
                    'min_service_months' => $data['min_service_months'],
                ],
                $data
            );
        }

        $this->command->info('  Created '.count($entitlements).' leave entitlement rules.');
    }

    /**
     * Seed default work schedules.
     */
    private function seedWorkSchedules(): void
    {
        $this->command->info('Seeding work schedules...');

        WorkSchedule::query()->firstOrCreate(
            ['name' => 'Office Hours'],
            [
                'type' => 'fixed',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'break_duration_minutes' => 60,
                'min_hours_per_day' => 8.0,
                'grace_period_minutes' => 10,
                'working_days' => [1, 2, 3, 4, 5],
                'is_default' => true,
            ]
        );

        WorkSchedule::query()->firstOrCreate(
            ['name' => 'Flexible Hours'],
            [
                'type' => 'flexible',
                'start_time' => null,
                'end_time' => null,
                'break_duration_minutes' => 60,
                'min_hours_per_day' => 8.0,
                'grace_period_minutes' => 0,
                'working_days' => [1, 2, 3, 4, 5],
                'is_default' => false,
            ]
        );

        $this->command->info('  Created 2 work schedules.');
    }

    /**
     * Seed Malaysian public holidays for 2026.
     */
    private function seedHolidays(): void
    {
        $this->command->info('Seeding 2026 Malaysian holidays...');

        $holidays = [
            ['name' => "New Year's Day", 'date' => '2026-01-01', 'type' => 'national'],
            ['name' => 'Thaipusam', 'date' => '2026-01-29', 'type' => 'national'],
            ['name' => 'Federal Territory Day', 'date' => '2026-02-01', 'type' => 'state', 'states' => ['kl', 'putrajaya', 'labuan']],
            ['name' => 'Israk & Mikraj', 'date' => '2026-02-01', 'type' => 'national'],
            ['name' => 'Nuzul Al-Quran', 'date' => '2026-03-20', 'type' => 'national'],
            ['name' => 'Hari Raya Aidilfitri', 'date' => '2026-03-31', 'type' => 'national'],
            ['name' => 'Hari Raya Aidilfitri (2nd Day)', 'date' => '2026-04-01', 'type' => 'national'],
            ['name' => 'Labour Day', 'date' => '2026-05-01', 'type' => 'national'],
            ['name' => "Agong's Birthday", 'date' => '2026-06-07', 'type' => 'national'],
            ['name' => 'Hari Raya Haji', 'date' => '2026-06-07', 'type' => 'national'],
            ['name' => 'Awal Muharram', 'date' => '2026-06-28', 'type' => 'national'],
            ['name' => 'Merdeka Day', 'date' => '2026-08-31', 'type' => 'national'],
            ['name' => 'Maulidur Rasul', 'date' => '2026-09-06', 'type' => 'national'],
            ['name' => 'Malaysia Day', 'date' => '2026-09-16', 'type' => 'national'],
            ['name' => 'Deepavali', 'date' => '2026-10-27', 'type' => 'national'],
            ['name' => 'Christmas Day', 'date' => '2026-12-25', 'type' => 'national'],
        ];

        foreach ($holidays as $data) {
            $exists = Holiday::query()
                ->where('name', $data['name'])
                ->whereDate('date', $data['date'])
                ->exists();

            if (! $exists) {
                Holiday::query()->create(array_merge($data, ['year' => 2026]));
            }
        }

        $this->command->info('  Created '.count($holidays).' holidays.');
    }

    /**
     * Assign default work schedule to all active employees.
     */
    private function assignDefaultSchedules(): void
    {
        $this->command->info('Assigning default schedule to employees...');

        $defaultSchedule = WorkSchedule::query()->where('is_default', true)->first();

        if (! $defaultSchedule) {
            $this->command->warn('  No default schedule found. Skipping.');

            return;
        }

        $employees = Employee::query()
            ->whereIn('status', ['active', 'probation'])
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            EmployeeSchedule::query()->firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'work_schedule_id' => $defaultSchedule->id,
                ],
                [
                    'effective_from' => $employee->join_date,
                ]
            );
            $count++;
        }

        $this->command->info("  Assigned schedule to {$count} employees.");
    }

    /**
     * Set up department approvers (first employee in each department).
     */
    private function seedDepartmentApprovers(): void
    {
        $this->command->info('Setting up department approvers...');

        $departments = Department::query()->get();
        $count = 0;

        foreach ($departments as $department) {
            $firstEmployee = Employee::query()
                ->where('department_id', $department->id)
                ->whereIn('status', ['active', 'probation'])
                ->orderBy('id')
                ->first();

            if (! $firstEmployee) {
                continue;
            }

            foreach (['overtime', 'leave'] as $type) {
                DepartmentApprover::query()->firstOrCreate(
                    [
                        'department_id' => $department->id,
                        'approval_type' => $type,
                    ],
                    [
                        'approver_employee_id' => $firstEmployee->id,
                    ]
                );
            }
            $count++;
        }

        $this->command->info("  Set approvers for {$count} departments.");
    }

    /**
     * Initialize leave balances for current year (2026).
     */
    private function initializeLeaveBalances(): void
    {
        $this->command->info('Initializing leave balances for 2026...');

        $year = 2026;
        $leaveTypes = LeaveType::query()->where('is_active', true)->get();
        $employees = Employee::query()
            ->whereIn('status', ['active', 'probation'])
            ->get();

        $count = 0;

        foreach ($employees as $employee) {
            $serviceMonths = Carbon::parse($employee->join_date)->diffInMonths(Carbon::create($year, 1, 1));

            foreach ($leaveTypes as $leaveType) {
                // Skip gender-restricted leave types
                if ($leaveType->gender_restriction && $leaveType->gender_restriction !== $employee->gender) {
                    continue;
                }

                $entitlement = $this->findMatchingEntitlement($leaveType, $employee->employment_type, $serviceMonths);

                if (! $entitlement) {
                    continue;
                }

                $entitledDays = $entitlement->days_per_year;

                // Apply proration for employees who joined mid-year
                if ($entitlement->is_prorated) {
                    $joinDate = Carbon::parse($employee->join_date);
                    if ($joinDate->year === $year) {
                        $remainingMonths = 12 - $joinDate->month + 1;
                        $entitledDays = round(($entitlement->days_per_year / 12) * $remainingMonths, 1);
                    }
                }

                LeaveBalance::query()->firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $year,
                    ],
                    [
                        'entitled_days' => $entitledDays,
                        'carried_forward_days' => 0,
                        'used_days' => 0,
                        'pending_days' => 0,
                        'available_days' => $entitledDays,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("  Created {$count} leave balance records.");
    }

    /**
     * Find matching leave entitlement for an employee.
     */
    private function findMatchingEntitlement(LeaveType $leaveType, string $employmentType, int $serviceMonths): ?LeaveEntitlement
    {
        // Try specific employment type first, then 'all'
        $entitlement = LeaveEntitlement::query()
            ->where('leave_type_id', $leaveType->id)
            ->where('employment_type', $employmentType)
            ->where('min_service_months', '<=', $serviceMonths)
            ->where(function ($query) use ($serviceMonths) {
                $query->whereNull('max_service_months')
                    ->orWhere('max_service_months', '>=', $serviceMonths);
            })
            ->first();

        if ($entitlement) {
            return $entitlement;
        }

        return LeaveEntitlement::query()
            ->where('leave_type_id', $leaveType->id)
            ->where('employment_type', 'all')
            ->where('min_service_months', '<=', $serviceMonths)
            ->where(function ($query) use ($serviceMonths) {
                $query->whereNull('max_service_months')
                    ->orWhere('max_service_months', '>=', $serviceMonths);
            })
            ->first();
    }

    /**
     * Seed attendance logs for past 30 working days.
     */
    private function seedAttendanceLogs(): void
    {
        $this->command->info('Seeding attendance logs (past 30 working days)...');

        $employees = Employee::query()
            ->whereIn('status', ['active', 'probation'])
            ->get();

        $defaultSchedule = WorkSchedule::query()->where('is_default', true)->first();
        $holidays = Holiday::query()
            ->where('year', 2026)
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $workingDays = $this->getWorkingDays(30, $holidays);
        $adminUser = User::query()->where('email', 'admin@example.com')->first();
        $totalLogs = 0;

        foreach ($employees as $employee) {
            foreach ($workingDays as $date) {
                $rand = fake()->numberBetween(1, 100);

                $existingLog = AttendanceLog::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', $date)
                    ->exists();

                if ($existingLog) {
                    continue;
                }

                if ($rand <= 5) {
                    // 5% absent
                    AttendanceLog::query()->create([
                        'employee_id' => $employee->id,
                        'date' => $date,
                        'status' => 'absent',
                        'remarks' => 'Absent without notice',
                    ]);
                } elseif ($rand <= 15) {
                    // 10% late
                    $lateMinutes = fake()->numberBetween(5, 30);
                    $clockIn = Carbon::parse($date.' 09:00')->addMinutes($lateMinutes);
                    $clockOut = Carbon::parse($date.' 18:00')->addMinutes(fake()->numberBetween(0, 15));
                    $workMinutes = (int) $clockIn->diffInMinutes($clockOut) - ($defaultSchedule->break_duration_minutes ?? 60);

                    AttendanceLog::query()->create([
                        'employee_id' => $employee->id,
                        'date' => $date,
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'status' => 'late',
                        'late_minutes' => $lateMinutes,
                        'total_work_minutes' => max(0, $workMinutes),
                        'approved_by' => $adminUser?->id,
                    ]);
                } else {
                    // 85% present on time
                    $clockIn = Carbon::parse($date.' 09:00')->subMinutes(fake()->numberBetween(0, 10));
                    $clockOut = Carbon::parse($date.' 18:00')->addMinutes(fake()->numberBetween(0, 10));
                    $workMinutes = (int) $clockIn->diffInMinutes($clockOut) - ($defaultSchedule->break_duration_minutes ?? 60);

                    AttendanceLog::query()->create([
                        'employee_id' => $employee->id,
                        'date' => $date,
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'status' => 'present',
                        'total_work_minutes' => max(0, $workMinutes),
                    ]);
                }
                $totalLogs++;
            }
        }

        $this->command->info("  Created {$totalLogs} attendance log records.");
    }

    /**
     * Get a list of working days going back from today.
     *
     * @param  array<string>  $holidays
     * @return array<string>
     */
    private function getWorkingDays(int $count, array $holidays): array
    {
        $days = [];
        $date = Carbon::parse('2026-03-27');

        while (count($days) < $count) {
            $date = $date->subDay();

            if ($date->isWeekend()) {
                continue;
            }

            if (in_array($date->format('Y-m-d'), $holidays)) {
                continue;
            }

            $days[] = $date->format('Y-m-d');
        }

        return array_reverse($days);
    }

    /**
     * Seed sample overtime requests.
     */
    private function seedOvertimeRequests(): void
    {
        $this->command->info('Seeding sample overtime requests...');

        $employees = Employee::query()
            ->whereIn('status', ['active', 'probation'])
            ->inRandomOrder()
            ->limit(10)
            ->get();

        if ($employees->count() < 10) {
            $this->command->warn('  Not enough employees for OT requests. Skipping.');

            return;
        }

        $adminUser = User::query()->where('email', 'admin@example.com')->first();

        $statuses = [
            'pending', 'pending', 'pending',
            'approved', 'approved', 'approved',
            'completed', 'completed',
            'rejected', 'rejected',
        ];

        foreach ($employees as $index => $employee) {
            $status = $statuses[$index];
            $requestedDate = Carbon::parse('2026-03-27')->subDays(fake()->numberBetween(1, 20));
            $startTime = '18:30';
            $endTime = fake()->randomElement(['20:00', '20:30', '21:00', '21:30']);
            $estimatedHours = round(Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime)) / 60, 1);

            $data = [
                'employee_id' => $employee->id,
                'requested_date' => $requestedDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'estimated_hours' => $estimatedHours,
                'reason' => fake()->randomElement([
                    'Urgent client deliverable deadline',
                    'System deployment scheduled after hours',
                    'Month-end closing tasks',
                    'Marketing campaign launch preparation',
                    'Inventory stocktake for warehouse',
                    'Data migration and testing',
                    'Customer support backlog clearance',
                    'Financial report preparation',
                    'Server maintenance window',
                    'Event setup and coordination',
                ]),
                'status' => $status,
            ];

            if (in_array($status, ['approved', 'completed'])) {
                $data['approved_by'] = $adminUser?->id;
                $data['approved_at'] = $requestedDate->copy()->subDays(1);
            }

            if ($status === 'completed') {
                $actualHours = $estimatedHours + fake()->randomFloat(1, -0.5, 0.5);
                $data['actual_hours'] = max(0.5, round($actualHours, 1));
                $data['replacement_hours_earned'] = $data['actual_hours'];
            }

            if ($status === 'rejected') {
                $data['approved_by'] = $adminUser?->id;
                $data['approved_at'] = $requestedDate->copy()->subDays(1);
                $data['rejection_reason'] = fake()->randomElement([
                    'Insufficient justification for overtime',
                    'Task can be completed during regular hours',
                ]);
            }

            OvertimeRequest::query()->create($data);
        }

        $this->command->info('  Created 10 overtime requests.');
    }

    /**
     * Seed sample leave requests and update balances accordingly.
     */
    private function seedLeaveRequests(): void
    {
        $this->command->info('Seeding sample leave requests...');

        $employees = Employee::query()
            ->whereIn('status', ['active', 'probation'])
            ->inRandomOrder()
            ->limit(10)
            ->get();

        if ($employees->count() < 10) {
            $this->command->warn('  Not enough employees for leave requests. Skipping.');

            return;
        }

        $adminUser = User::query()->where('email', 'admin@example.com')->first();
        $alType = LeaveType::query()->where('code', 'AL')->first();
        $mcType = LeaveType::query()->where('code', 'MC')->first();

        $statuses = [
            'pending', 'pending', 'pending',
            'approved', 'approved', 'approved', 'approved',
            'rejected', 'rejected',
            'cancelled',
        ];

        $leaveTypeChoices = [
            $alType, $mcType, $alType, $alType, $mcType,
            $alType, $alType, $mcType, $alType, $alType,
        ];

        foreach ($employees as $index => $employee) {
            $status = $statuses[$index];
            $leaveType = $leaveTypeChoices[$index];
            $startDate = Carbon::parse('2026-03-27')->addDays(fake()->numberBetween(1, 30));

            while ($startDate->isWeekend()) {
                $startDate->addDay();
            }

            $totalDays = fake()->randomElement([1.0, 1.0, 2.0, 3.0, 0.5]);
            $isHalfDay = $totalDays === 0.5;

            if ($isHalfDay) {
                $endDate = $startDate->copy();
                $totalDays = 0.5;
            } else {
                $endDate = $startDate->copy()->addDays((int) $totalDays - 1);
                while ($endDate->isWeekend()) {
                    $endDate->addDay();
                }
            }

            $data = [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'is_half_day' => $isHalfDay,
                'half_day_period' => $isHalfDay ? fake()->randomElement(['morning', 'afternoon']) : null,
                'reason' => fake()->randomElement([
                    'Family vacation',
                    'Medical appointment',
                    'Personal matters',
                    'Feeling unwell, need rest',
                    'Family emergency',
                    'Home renovation',
                    'Wedding attendance',
                    'Religious obligation',
                    'Child school event',
                    'Annual medical checkup',
                ]),
                'status' => $status,
            ];

            if ($leaveType->code === 'MC') {
                $data['reason'] = fake()->randomElement([
                    'Fever and flu symptoms',
                    'Food poisoning',
                    'Medical appointment and follow-up',
                    'Dental procedure',
                ]);
            }

            if (in_array($status, ['approved', 'rejected'])) {
                $data['approved_by'] = $adminUser?->id;
                $data['approved_at'] = now()->subDays(fake()->numberBetween(1, 5));
            }

            if ($status === 'rejected') {
                $data['rejection_reason'] = fake()->randomElement([
                    'Insufficient leave balance',
                    'Critical project deadline - please reschedule',
                ]);
            }

            LeaveRequest::query()->create($data);

            // Update leave balances for approved and pending requests
            if (in_array($status, ['approved', 'pending'])) {
                $balance = LeaveBalance::query()
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', 2026)
                    ->first();

                if ($balance) {
                    if ($status === 'approved') {
                        $balance->used_days += $totalDays;
                    } else {
                        $balance->pending_days += $totalDays;
                    }
                    $balance->available_days = $balance->entitled_days + $balance->carried_forward_days - $balance->used_days - $balance->pending_days;
                    $balance->save();
                }
            }
        }

        $this->command->info('  Created 10 leave requests.');
    }
}
