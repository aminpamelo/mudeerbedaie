<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class HrInitializeLeaveBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:initialize-leave-balances {--year=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize leave balances for all active employees';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: Carbon::now()->year);
        $previousYear = $year - 1;
        $count = 0;

        $employees = Employee::query()
            ->where('status', 'active')
            ->get();

        $leaveTypes = LeaveType::active()->get();

        foreach ($employees as $employee) {
            $serviceMonths = $employee->join_date
                ? (int) $employee->join_date->diffInMonths(Carbon::create($year, 1, 1))
                : 0;

            $joinedThisYear = $employee->join_date
                && $employee->join_date->year === $year;

            foreach ($leaveTypes as $leaveType) {
                $entitlement = LeaveEntitlement::query()
                    ->where('leave_type_id', $leaveType->id)
                    ->where(function ($query) use ($employee) {
                        $query->where('employment_type', $employee->employment_type)
                            ->orWhere('employment_type', 'all');
                    })
                    ->where('min_service_months', '<=', $serviceMonths)
                    ->where(function ($query) use ($serviceMonths) {
                        $query->whereNull('max_service_months')
                            ->orWhere('max_service_months', '>=', $serviceMonths);
                    })
                    ->orderByDesc('min_service_months')
                    ->first();

                if (! $entitlement) {
                    continue;
                }

                $entitledDays = (float) $entitlement->days_per_year;

                if ($entitlement->is_prorated && $joinedThisYear) {
                    $remainingMonths = 12 - $employee->join_date->month + 1;
                    $entitledDays = round($entitlement->days_per_year * ($remainingMonths / 12), 1);
                }

                $carryForward = 0.0;
                $previousBalance = LeaveBalance::query()
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $previousYear)
                    ->first();

                if ($previousBalance && $entitlement->carry_forward_max > 0) {
                    $carryForward = min(
                        (float) $previousBalance->available_days,
                        (float) $entitlement->carry_forward_max
                    );
                    $carryForward = max($carryForward, 0);
                }

                $availableDays = $entitledDays + $carryForward;

                LeaveBalance::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $year,
                    ],
                    [
                        'entitled_days' => $entitledDays,
                        'carried_forward_days' => $carryForward,
                        'available_days' => $availableDays,
                    ]
                );

                $count++;
            }
        }

        $this->info("Initialized/updated {$count} leave balances for year {$year}.");

        return self::SUCCESS;
    }
}
