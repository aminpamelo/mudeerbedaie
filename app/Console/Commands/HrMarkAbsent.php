<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class HrMarkAbsent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:mark-absent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark employees absent who did not clock in today';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::now();
        $todayDayOfWeek = $today->dayOfWeekIso;
        $todayDate = $today->toDateString();

        $isHoliday = Holiday::forDate($todayDate)->exists();

        if ($isHoliday) {
            $this->info('Today is a holiday. No employees marked absent.');

            return self::SUCCESS;
        }

        $employees = Employee::query()
            ->where('status', 'active')
            ->whereHas('currentSchedule')
            ->with(['currentSchedule.workSchedule'])
            ->get();

        $markedCount = 0;

        foreach ($employees as $employee) {
            $schedule = $employee->currentSchedule;

            if (! $schedule || ! $schedule->workSchedule) {
                continue;
            }

            $workingDays = $schedule->workSchedule->working_days ?? [];

            if (! in_array($todayDayOfWeek, $workingDays)) {
                continue;
            }

            $hasApprovedLeave = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->where('start_date', '<=', $todayDate)
                ->where('end_date', '>=', $todayDate)
                ->exists();

            if ($hasApprovedLeave) {
                continue;
            }

            $hasAttendance = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $todayDate)
                ->exists();

            if ($hasAttendance) {
                continue;
            }

            $attendanceLog = AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $todayDate,
                'status' => 'absent',
            ]);

            AttendancePenalty::create([
                'employee_id' => $employee->id,
                'attendance_log_id' => $attendanceLog->id,
                'penalty_type' => 'absent_without_leave',
                'penalty_minutes' => 0,
                'month' => $today->month,
                'year' => $today->year,
                'notes' => 'Auto-generated: absent without leave on '.$todayDate,
            ]);

            $markedCount++;
        }

        $this->info("Marked {$markedCount} employees as absent.");

        return self::SUCCESS;
    }
}
