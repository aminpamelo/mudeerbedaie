<?php

namespace App\Console\Commands\Hr;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\Hr\TeamLeaveAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendTeamLeaveAlerts extends Command
{
    protected $signature = 'hr:send-team-leave-alerts';

    protected $description = 'Alert managers about team members on leave today';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        $departments = Department::all();
        $admins = User::where('role', 'admin')->get();

        $count = 0;
        foreach ($departments as $department) {
            $onLeave = LeaveRequest::query()
                ->whereHas('employee', fn ($q) => $q->where('department_id', $department->id))
                ->where('status', 'approved')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->count();

            if ($onLeave > 0) {
                foreach ($admins as $admin) {
                    $admin->notify(new TeamLeaveAlert($department->name, $onLeave, $today));
                }
                $count++;
            }
        }

        $this->info("Sent team leave alerts for {$count} departments.");

        return self::SUCCESS;
    }
}
