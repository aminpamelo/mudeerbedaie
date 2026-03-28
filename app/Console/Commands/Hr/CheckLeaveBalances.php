<?php

namespace App\Console\Commands\Hr;

use App\Models\LeaveBalance;
use App\Notifications\Hr\LeaveBalanceLow;
use Illuminate\Console\Command;

class CheckLeaveBalances extends Command
{
    protected $signature = 'hr:check-leave-balances';

    protected $description = 'Notify employees with low leave balances';

    public function handle(): int
    {
        $balances = LeaveBalance::query()
            ->with(['employee.user', 'leaveType'])
            ->where('year', now()->year)
            ->get()
            ->filter(function ($balance) {
                $remaining = $balance->entitled_days - $balance->used_days - $balance->pending_days;

                return $remaining > 0 && $remaining <= 3;
            });

        $count = 0;
        foreach ($balances as $balance) {
            if ($balance->employee?->user) {
                $balance->employee->user->notify(new LeaveBalanceLow($balance));
                $count++;
            }
        }

        $this->info("Sent {$count} low balance notifications.");

        return self::SUCCESS;
    }
}
