<?php

namespace App\Console\Commands\Hr;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Notifications\Hr\LateClockInAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendLateAlerts extends Command
{
    protected $signature = 'hr:send-late-alerts';

    protected $description = 'Send alerts to managers when employees clock in late';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        $lateLogs = AttendanceLog::query()
            ->with(['employee.user', 'employee.department'])
            ->where('date', $today)
            ->where('late_minutes', '>=', 15)
            ->whereNotNull('clock_in')
            ->get();

        $count = 0;
        $admins = User::where('role', 'admin')->get();

        foreach ($lateLogs as $log) {
            $minutesLate = $log->late_minutes ?? 0;

            foreach ($admins as $admin) {
                $admin->notify(new LateClockInAlert($log, $minutesLate));
            }

            $count++;
        }

        $this->info("Sent late alerts for {$count} employees.");

        return self::SUCCESS;
    }
}
