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
     * All employees' balances for a given year, grouped by employee.
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $query = Employee::query()
            ->with(['department:id,name'])
            ->where('status', 'active');

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('full_name')->paginate(15);

        $employeeIds = $employees->pluck('id');

        $balances = LeaveBalance::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('year', $year)
            ->get()
            ->groupBy('employee_id');

        $employees->getCollection()->transform(function ($employee) use ($balances) {
            $employeeBalances = $balances->get($employee->id, collect());

            $employee->setAttribute('balances', $employeeBalances->map(fn ($b) => [
                'leave_type_id' => $b->leave_type_id,
                'entitled' => $b->entitled_days,
                'used' => $b->used_days,
                'pending' => $b->pending_days ?? 0,
                'carried_forward' => $b->carried_forward_days ?? 0,
                'available' => $b->available_days,
            ])->values());

            return $employee;
        });

        return response()->json($employees);
    }

    /**
     * Single employee's detailed balance breakdown.
     */
    public function show(int $employeeId): JsonResponse
    {
        $year = request()->get('year', now()->year);

        $employee = Employee::with('department:id,name')->findOrFail($employeeId);

        $balances = LeaveBalance::query()
            ->with('leaveType:id,name,code')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get()
            ->map(fn ($b) => [
                'leave_type_id' => $b->leave_type_id,
                'leave_type_name' => $b->leaveType?->name,
                'entitled' => $b->entitled_days,
                'used' => $b->used_days,
                'pending' => $b->pending_days ?? 0,
                'carry_forward' => $b->carried_forward_days ?? 0,
                'available' => $b->available_days,
            ]);

        return response()->json([
            'data' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'department' => $employee->department,
                'balances' => $balances,
            ],
        ]);
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
                            $employeeTypes = is_array($employee->employment_type) ? $employee->employment_type : [$employee->employment_type];
                            $typeMatch = $ent->employment_type === 'all' || in_array($ent->employment_type, $employeeTypes);
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
