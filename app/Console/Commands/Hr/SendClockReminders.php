<?php

namespace App\Console\Commands\Hr;

use App\Models\AttendanceLog;
use App\Models\EmployeeSchedule;
use App\Notifications\Hr\ClockInReminder;
use App\Notifications\Hr\ClockOutReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendClockReminders extends Command
{
    protected $signature = 'hr:send-clock-reminders';

    protected $description = 'Send clock-in and clock-out reminders to employees';

    public function handle(): int
    {
        $now = Carbon::now();
        $today = $now->toDateString();

        $schedules = EmployeeSchedule::query()
            ->with(['employee.user', 'workSchedule'])
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->get();

        $clockInCount = 0;
        $clockOutCount = 0;

        foreach ($schedules as $schedule) {
            if (! $schedule->employee?->user || ! $schedule->workSchedule) {
                continue;
            }

            $startTime = $schedule->workSchedule->start_time;
            $endTime = $schedule->workSchedule->end_time;

            if (! $startTime || ! $endTime) {
                continue;
            }

            // Clock-in reminder: 15 minutes before shift start
            $shiftStart = Carbon::parse($today.' '.$startTime);
            $reminderWindow = $shiftStart->copy()->subMinutes(15);

            if ($now->between($reminderWindow, $shiftStart)) {
                $hasClockedIn = AttendanceLog::where('employee_id', $schedule->employee_id)
                    ->where('date', $today)
                    ->whereNotNull('clock_in')
                    ->exists();

                if (! $hasClockedIn) {
                    $schedule->employee->user->notify(new ClockInReminder($startTime));
                    $clockInCount++;
                }
            }

            // Clock-out reminder: 15 minutes before shift end
            $shiftEnd = Carbon::parse($today.' '.$endTime);
            $clockOutWindow = $shiftEnd->copy()->subMinutes(15);

            if ($now->between($clockOutWindow, $shiftEnd)) {
                $hasClockedOut = AttendanceLog::where('employee_id', $schedule->employee_id)
                    ->where('date', $today)
                    ->whereNotNull('clock_out')
                    ->exists();

                if (! $hasClockedOut) {
                    $schedule->employee->user->notify(new ClockOutReminder($endTime));
                    $clockOutCount++;
                }
            }
        }

        $this->info("Sent {$clockInCount} clock-in and {$clockOutCount} clock-out reminders.");

        return self::SUCCESS;
    }
}
