<?php

namespace App\Console\Commands;

use App\Models\AttendancePenalty;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class HrPenaltySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:penalty-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly attendance penalty summary';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $previousMonth = Carbon::now()->subMonth();
        $month = $previousMonth->month;
        $year = $previousMonth->year;

        $totalPenalties = AttendancePenalty::query()
            ->where('month', $month)
            ->where('year', $year)
            ->count();

        $flaggedEmployees = Employee::query()
            ->where('status', 'active')
            ->whereHas('attendancePenalties', function ($query) use ($month, $year) {
                $query->where('month', $month)
                    ->where('year', $year)
                    ->where('penalty_type', 'late_arrival');
            }, '>=', 3)
            ->count();

        $this->info("Penalty Summary for {$previousMonth->format('F Y')}:");
        $this->info("  Total penalties: {$totalPenalties}");
        $this->info("  Employees flagged (3+ late arrivals): {$flaggedEmployees}");

        return self::SUCCESS;
    }
}
