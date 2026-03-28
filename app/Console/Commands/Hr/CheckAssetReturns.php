<?php

namespace App\Console\Commands\Hr;

use App\Models\AssetAssignment;
use App\Notifications\Hr\AssetReturnRequested;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckAssetReturns extends Command
{
    protected $signature = 'hr:check-asset-returns';

    protected $description = 'Notify employees about upcoming asset return deadlines';

    public function handle(): int
    {
        $warningDate = Carbon::now()->addDays(7)->toDateString();

        $assignments = AssetAssignment::query()
            ->with(['employee.user', 'asset'])
            ->whereNull('returned_at')
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<=', $warningDate)
            ->whereDate('expected_return_date', '>=', Carbon::today())
            ->get();

        $count = 0;
        foreach ($assignments as $assignment) {
            if ($assignment->employee?->user) {
                $daysLeft = (int) Carbon::now()->diffInDays($assignment->expected_return_date, false);
                $assignment->employee->user->notify(new AssetReturnRequested($assignment, max(0, $daysLeft)));
                $count++;
            }
        }

        $this->info("Sent {$count} asset return reminders.");

        return self::SUCCESS;
    }
}
