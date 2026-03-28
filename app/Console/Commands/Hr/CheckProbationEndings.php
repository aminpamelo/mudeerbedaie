<?php

namespace App\Console\Commands\Hr;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\Hr\ProbationEnding;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckProbationEndings extends Command
{
    protected $signature = 'hr:check-probation-endings';

    protected $description = 'Notify managers about employees whose probation is ending soon';

    public function handle(): int
    {
        $warningDate = Carbon::now()->addDays(30)->toDateString();

        $employees = Employee::query()
            ->where('employment_status', 'probation')
            ->whereNotNull('probation_end_date')
            ->whereDate('probation_end_date', '<=', $warningDate)
            ->whereDate('probation_end_date', '>=', Carbon::today())
            ->get();

        $admins = User::where('role', 'admin')->get();
        $count = 0;

        foreach ($employees as $employee) {
            $daysLeft = (int) Carbon::now()->diffInDays($employee->probation_end_date, false);

            foreach ($admins as $admin) {
                $admin->notify(new ProbationEnding($employee, max(0, $daysLeft)));
            }
            $count++;
        }

        $this->info("Sent probation ending alerts for {$count} employees.");

        return self::SUCCESS;
    }
}
