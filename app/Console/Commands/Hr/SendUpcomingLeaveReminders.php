<?php

namespace App\Console\Commands\Hr;

use App\Models\LeaveRequest;
use App\Notifications\Hr\UpcomingLeaveReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendUpcomingLeaveReminders extends Command
{
    protected $signature = 'hr:send-upcoming-leave-reminders';

    protected $description = 'Remind employees about leave starting tomorrow';

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $requests = LeaveRequest::query()
            ->with(['employee.user', 'leaveType'])
            ->where('status', 'approved')
            ->where('start_date', $tomorrow)
            ->get();

        $count = 0;
        foreach ($requests as $request) {
            if ($request->employee?->user) {
                $request->employee->user->notify(new UpcomingLeaveReminder($request));
                $count++;
            }
        }

        $this->info("Sent {$count} upcoming leave reminders.");

        return self::SUCCESS;
    }
}
