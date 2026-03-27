<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrLeaveBalanceController extends Controller
{
    /**
     * All employees' balances for a given year.
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $query = LeaveBalance::query()
            ->with(['employee.department', 'leaveType'])
            ->where('year', $year);

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($employmentType = $request->get('employment_type')) {
            $query->whereHas('employee', fn ($q) => $q->where('employment_type', $employmentType));
        }

        $balances = $query->orderBy('employee_id')->paginate(15);

        return response()->json($balances);
    }

    /**
     * Single employee's detailed balance breakdown.
     */
    public function show(int $employeeId): JsonResponse
    {
        $year = request()->get('year', now()->year);

        $balances = LeaveBalance::query()
            ->with(['employee.department', 'leaveType'])
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get();

        return response()->json(['data' => $balances]);
    }

    /**
     * Initialize balances for all active employees for a given year.
     */
    public function initialize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2050'],
        ]);

        $year = $validated['year'];

        return DB::transaction(function () use ($year) {
            $employees = Employee::query()->where('status', 'active')->get();
            $leaveTypes = LeaveType::query()->where('is_active', true)->get();
            $entitlements = LeaveEntitlement::all();
            $initialized = 0;

            foreach ($employees as $employee) {
                $serviceMonths = $employee->join_date
                    ? (int) $employee->join_date->diffInMonths(Carbon::create($year, 1, 1))
                    : 0;

                foreach ($leaveTypes as $leaveType) {
                    if ($leaveType->gender_restriction && $leaveType->gender_restriction !== $employee->gender) {
                        continue;
                    }

                    $matchingEntitlement = $entitlements
                        ->where('leave_type_id', $leaveType->id)
                        ->filter(function ($ent) use ($employee, $serviceMonths) {
                            $typeMatch = $ent->employment_type === 'all' || $ent->employment_type === $employee->employment_type;
                            $minMatch = $serviceMonths >= $ent->min_service_months;
                            $maxMatch = $ent->max_service_months === null || $serviceMonths <= $ent->max_service_months;

                            return $typeMatch && $minMatch && $maxMatch;
                        })
                        ->sortByDesc('days_per_year')
                        ->first();

                    if (! $matchingEntitlement) {
                        continue;
                    }

                    $entitledDays = $matchingEntitlement->days_per_year;

                    if ($matchingEntitlement->is_prorated && $employee->join_date) {
                        $joinYear = $employee->join_date->year;
                        if ($joinYear == $year) {
                            $remainingMonths = 12 - $employee->join_date->month + 1;
                            $entitledDays = round(($matchingEntitlement->days_per_year / 12) * $remainingMonths, 1);
                        }
                    }

                    $carryForward = 0;
                    $previousBalance = LeaveBalance::query()
                        ->where('employee_id', $employee->id)
                        ->where('leave_type_id', $leaveType->id)
                        ->where('year', $year - 1)
                        ->first();

                    if ($previousBalance && $matchingEntitlement->carry_forward_max > 0) {
                        $remaining = $previousBalance->entitled_days + $previousBalance->carried_forward_days - $previousBalance->used_days;
                        $carryForward = min(max($remaining, 0), $matchingEntitlement->carry_forward_max);
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

                    $initialized++;
                }
            }

            return response()->json([
                'message' => "{$initialized} leave balance(s) initialized for year {$year}.",
            ], 201);
        });
    }

    /**
     * Manual adjustment of a leave balance.
     */
    public function adjust(Request $request, LeaveBalance $leaveBalance): JsonResponse
    {
        $validated = $request->validate([
            'adjustment_days' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $leaveBalance->update([
            'available_days' => $leaveBalance->available_days + $validated['adjustment_days'],
        ]);

        return response()->json([
            'data' => $leaveBalance->fresh(['employee', 'leaveType']),
            'message' => 'Leave balance adjusted successfully.',
        ]);
    }

    /**
     * Export leave balances as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $year = $request->get('year', now()->year);

        $balances = LeaveBalance::query()
            ->with(['employee.department', 'leaveType'])
            ->where('year', $year)
            ->orderBy('employee_id')
            ->get();

        return response()->streamDownload(function () use ($balances) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee ID', 'Employee Name', 'Department', 'Leave Type',
                'Entitled Days', 'Carried Forward', 'Used Days', 'Pending Days', 'Available Days',
            ]);

            foreach ($balances as $balance) {
                fputcsv($handle, [
                    $balance->employee?->employee_id,
                    $balance->employee?->full_name,
                    $balance->employee?->department?->name,
                    $balance->leaveType?->name,
                    $balance->entitled_days,
                    $balance->carried_forward_days,
                    $balance->used_days,
                    $balance->pending_days,
                    $balance->available_days,
                ]);
            }

            fclose($handle);
        }, "leave-balances-{$year}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
