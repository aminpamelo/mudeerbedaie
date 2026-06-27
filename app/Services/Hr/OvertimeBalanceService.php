<?php

namespace App\Services\Hr;

use App\Models\OvertimeAdjustment;
use App\Models\OvertimeClaimRequest;
use App\Models\OvertimeRequest;

/**
 * Single source of truth for an employee's overtime replacement balance.
 *
 * The balance is tracked in whole minutes and aggregates every channel that
 * adds to or draws down banked OT:
 *   + earned        completed OT requests (replacement_hours_earned)
 *   + adjustments   standalone admin add/deduct records (signed minutes)
 *   - claim used    approved OT claims taken as time off (duration_minutes)
 *   - leave used    OT consumed by replacement leave (replacement_hours_used)
 *
 * Both the employee self-service view and the admin overview read from here so
 * the "available" figure is identical on every surface.
 */
class OvertimeBalanceService
{
    /**
     * Balance for a single employee.
     *
     * @return array{earned_minutes:int, used_minutes:int, adjustment_minutes:int, available_minutes:int}
     */
    public function forEmployee(int $employeeId): array
    {
        return $this->forEmployees([$employeeId])[$employeeId] ?? $this->emptyBalance();
    }

    /**
     * Batch balances for many employees, keyed by employee id. Runs a fixed
     * number of grouped queries regardless of how many employees are passed.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, array{earned_minutes:int, used_minutes:int, adjustment_minutes:int, available_minutes:int}>
     */
    public function forEmployees(array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if (empty($employeeIds)) {
            return [];
        }

        $earnedHours = OvertimeRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'completed')
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(replacement_hours_earned) as total')
            ->pluck('total', 'employee_id');

        $leaveUsedHours = OvertimeRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(replacement_hours_used) as total')
            ->pluck('total', 'employee_id');

        $claimUsedMinutes = OvertimeClaimRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(duration_minutes) as total')
            ->pluck('total', 'employee_id');

        $adjustmentMinutes = OvertimeAdjustment::query()
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(minutes) as total')
            ->pluck('total', 'employee_id');

        $result = [];

        foreach ($employeeIds as $id) {
            $earnedMinutes = (int) round(((float) ($earnedHours[$id] ?? 0)) * 60);
            $usedMinutes = (int) ($claimUsedMinutes[$id] ?? 0)
                + (int) round(((float) ($leaveUsedHours[$id] ?? 0)) * 60);
            $adjMinutes = (int) ($adjustmentMinutes[$id] ?? 0);

            $result[$id] = [
                'earned_minutes' => $earnedMinutes,
                'used_minutes' => $usedMinutes,
                'adjustment_minutes' => $adjMinutes,
                'available_minutes' => $earnedMinutes + $adjMinutes - $usedMinutes,
            ];
        }

        return $result;
    }

    /**
     * @return array{earned_minutes:int, used_minutes:int, adjustment_minutes:int, available_minutes:int}
     */
    private function emptyBalance(): array
    {
        return [
            'earned_minutes' => 0,
            'used_minutes' => 0,
            'adjustment_minutes' => 0,
            'available_minutes' => 0,
        ];
    }
}
