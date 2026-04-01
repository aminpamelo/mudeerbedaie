<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ClockInRequest;
use App\Http\Requests\Hr\ClockOutRequest;
use App\Http\Requests\Hr\StoreOvertimeClaimRequest;
use App\Http\Requests\Hr\StoreOvertimeRequest;
use App\Models\AttendanceLog;
use App\Models\AttendancePenalty;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OvertimeClaimRequest;
use App\Models\OvertimeRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HrMyAttendanceController extends Controller
{
    /**
     * My attendance records for a given month/year.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $logs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $logs]);
    }

    /**
     * Clock in for today.
     */
    public function clockIn(ClockInRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $today = Carbon::today();

        $existingLog = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($existingLog && $existingLog->clock_in) {
            return response()->json(['message' => 'You have already clocked in today.'], 422);
        }

        $isWfh = $request->boolean('is_wfh');

        // Validate office location for non-WFH clock-ins
        if (! $isWfh && Setting::getValue('hr_require_location_office', false)) {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');

            if (is_null($latitude) || is_null($longitude)) {
                return response()->json([
                    'message' => 'Location is required for office clock-in. Please enable GPS.',
                ], 422);
            }

            $officeLat = (float) Setting::getValue('hr_office_latitude', 0);
            $officeLng = (float) Setting::getValue('hr_office_longitude', 0);
            $radiusMeters = (float) Setting::getValue('hr_office_radius_meters', 200);

            $distance = $this->calculateDistance($latitude, $longitude, $officeLat, $officeLng);

            if ($distance > $radiusMeters) {
                return response()->json([
                    'message' => 'You are too far from the office to clock in. Please move closer to the office location.',
                    'distance' => round($distance),
                    'max_radius' => $radiusMeters,
                ], 422);
            }
        }

        return DB::transaction(function () use ($request, $employee, $today, $existingLog, $isWfh) {
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store("attendance-photos/{$employee->id}", 'public');
            }

            $clockInTime = now();

            $schedule = EmployeeSchedule::query()
                ->where('employee_id', $employee->id)
                ->active()
                ->with('workSchedule')
                ->first();

            $status = $isWfh ? 'wfh' : 'present';
            $lateMinutes = 0;

            if ($schedule && $schedule->workSchedule && $schedule->workSchedule->start_time) {
                $scheduledStart = Carbon::parse($today->toDateString().' '.$schedule->workSchedule->start_time);
                $graceMinutes = $schedule->workSchedule->grace_period_minutes ?? 0;
                $scheduledStartWithGrace = $scheduledStart->copy()->addMinutes($graceMinutes);

                if ($clockInTime->gt($scheduledStartWithGrace) && ! $isWfh) {
                    $lateMinutes = (int) $scheduledStart->diffInMinutes($clockInTime);
                    $status = 'late';
                }
            }

            $log = $existingLog ?? new AttendanceLog;
            $log->fill([
                'employee_id' => $employee->id,
                'date' => $today->toDateString(),
                'clock_in' => $clockInTime,
                'clock_in_photo' => $photoPath,
                'clock_in_ip' => $request->ip(),
                'clock_in_latitude' => $request->input('latitude'),
                'clock_in_longitude' => $request->input('longitude'),
                'status' => $status,
                'late_minutes' => $lateMinutes,
            ]);
            $log->save();

            if ($lateMinutes > 0) {
                AttendancePenalty::create([
                    'employee_id' => $employee->id,
                    'attendance_log_id' => $log->id,
                    'penalty_type' => 'late_arrival',
                    'penalty_minutes' => $lateMinutes,
                    'month' => $today->month,
                    'year' => $today->year,
                    'notes' => "Late by {$lateMinutes} minutes.",
                ]);
            }

            return response()->json([
                'data' => $log,
                'message' => 'Clocked in successfully.',
                'is_late' => $lateMinutes > 0,
                'late_minutes' => $lateMinutes,
            ], 201);
        });
    }

    /**
     * Clock out for today.
     */
    public function clockOut(ClockOutRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $today = Carbon::today();

        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if (! $log || ! $log->clock_in) {
            return response()->json(['message' => 'You have not clocked in today.'], 422);
        }

        if ($log->clock_out) {
            return response()->json(['message' => 'You have already clocked out today.'], 422);
        }

        return DB::transaction(function () use ($request, $employee, $log, $today) {
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store("attendance-photos/{$employee->id}", 'public');
            }

            $clockOutTime = now();
            $clockInTime = Carbon::parse($log->clock_in);
            $totalWorkMinutes = (int) $clockInTime->diffInMinutes($clockOutTime);

            $earlyLeaveMinutes = 0;

            $schedule = EmployeeSchedule::query()
                ->where('employee_id', $employee->id)
                ->active()
                ->with('workSchedule')
                ->first();

            if ($schedule && $schedule->workSchedule && $schedule->workSchedule->end_time) {
                $scheduledEnd = Carbon::parse($today->toDateString().' '.$schedule->workSchedule->end_time);

                if ($clockOutTime->lt($scheduledEnd)) {
                    $earlyLeaveMinutes = (int) $clockOutTime->diffInMinutes($scheduledEnd);
                }
            }

            $log->update([
                'clock_out' => $clockOutTime,
                'clock_out_photo' => $photoPath,
                'clock_out_ip' => $request->ip(),
                'total_work_minutes' => $totalWorkMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
            ]);

            if ($earlyLeaveMinutes > 0) {
                AttendancePenalty::create([
                    'employee_id' => $employee->id,
                    'attendance_log_id' => $log->id,
                    'penalty_type' => 'early_departure',
                    'penalty_minutes' => $earlyLeaveMinutes,
                    'month' => $today->month,
                    'year' => $today->year,
                    'notes' => "Left {$earlyLeaveMinutes} minutes early.",
                ]);
            }

            return response()->json([
                'data' => $log->fresh(),
                'message' => 'Clocked out successfully.',
                'total_work_minutes' => $totalWorkMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
            ]);
        });
    }

    /**
     * My today's attendance status.
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->first();

        return response()->json(['data' => $log]);
    }

    /**
     * My current month stats.
     */
    public function summary(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $period = $request->get('period');

        if ($period === 'week') {
            return $this->weekSummary($employee);
        }

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $stats = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->select(
                DB::raw("SUM(CASE WHEN status IN ('present', 'wfh') THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count"),
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw('SUM(late_minutes) as total_late_minutes')
            )
            ->first();

        return response()->json([
            'data' => [
                'present' => (int) ($stats->present_count ?? 0),
                'late' => (int) ($stats->late_count ?? 0),
                'absent' => (int) ($stats->absent_count ?? 0),
                'total_late_minutes' => (int) ($stats->total_late_minutes ?? 0),
            ],
        ]);
    }

    /**
     * Get daily attendance statuses for the current week (Mon-Fri).
     */
    private function weekSummary(Employee $employee): JsonResponse
    {
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $logs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfWeek->toDateString(), $startOfWeek->copy()->addDays(4)->toDateString()])
            ->get()
            ->keyBy(fn ($log) => Carbon::parse($log->date)->dayOfWeekIso);

        $days = [];
        for ($i = 1; $i <= 5; $i++) {
            $log = $logs->get($i);
            $day = $startOfWeek->copy()->addDays($i - 1);
            $days[] = [
                'date' => $day->toDateString(),
                'status' => $log?->status ?? ($day->lte(Carbon::today()) ? 'absent' : 'none'),
            ];
        }

        return response()->json(['data' => ['days' => $days]]);
    }

    /**
     * My overtime requests list.
     */
    public function myOvertime(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $requests = OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('requested_date')
            ->paginate(15);

        return response()->json($requests);
    }

    /**
     * Submit an overtime request.
     */
    public function submitOvertime(StoreOvertimeRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $validated = $request->validated();

        $overtimeRequest = OvertimeRequest::create(array_merge($validated, [
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]));

        $overtimeRequest->load('employee.department');
        $notifiedUserIds = [];

        $approvers = \App\Models\DepartmentApprover::forDepartment(
            $overtimeRequest->employee->department_id
        )->forType('overtime')->with('approver.user')->get();

        foreach ($approvers as $deptApprover) {
            if ($deptApprover->approver?->user) {
                $deptApprover->approver->user->notify(
                    new \App\Notifications\Hr\OvertimeRequestSubmitted($overtimeRequest)
                );
                $notifiedUserIds[] = $deptApprover->approver->user->id;
            }
        }

        // Also notify admin users who weren't already notified as approvers
        $admins = \App\Models\User::where('role', 'admin')
            ->whereNotIn('id', $notifiedUserIds)
            ->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\Hr\OvertimeRequestSubmitted($overtimeRequest));
        }

        return response()->json([
            'data' => $overtimeRequest,
            'message' => 'Overtime request submitted successfully.',
        ], 201);
    }

    /**
     * My replacement hours balance.
     */
    public function overtimeBalance(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $balance = $this->computeOvertimeBalance($employee);
        $totalUsed = round($balance['total_used_minutes'] / 60, 1);

        return response()->json([
            'data' => [
                'total_earned' => $balance['total_earned'],
                'total_used' => $totalUsed,
                'available' => round($balance['total_earned'] - $totalUsed, 1),
            ],
        ]);
    }

    /**
     * List my OT claim requests.
     */
    public function myOvertimeClaims(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $claims = OvertimeClaimRequest::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('claim_date')
            ->paginate(15);

        return response()->json($claims);
    }

    /**
     * Submit a new OT claim request.
     */
    public function submitOvertimeClaim(StoreOvertimeClaimRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $validated = $request->validated();

        $claim = DB::transaction(function () use ($validated, $employee) {
            $balance = $this->computeOvertimeBalance($employee);

            if ($validated['duration_minutes'] > $balance['available_minutes']) {
                // We can't throw a validation exception from inside a transaction easily,
                // so return null to signal failure
                return null;
            }

            return OvertimeClaimRequest::create(array_merge($validated, [
                'employee_id' => $employee->id,
                'status' => 'pending',
            ]));
        });

        if (! $claim) {
            $balance = $this->computeOvertimeBalance($employee);

            return response()->json([
                'message' => 'Insufficient OT balance. You have '.round($balance['available_minutes'] / 60, 1).'h available.',
            ], 422);
        }

        // Notify OT approvers for this department
        $approvers = DepartmentApprover::forDepartment($employee->department_id)
            ->forType('overtime')
            ->with('approver.user')
            ->get();

        $notifiedUserIds = [];
        foreach ($approvers as $deptApprover) {
            if ($deptApprover->approver?->user) {
                $deptApprover->approver->user->notify(new \App\Notifications\Hr\OvertimeClaimSubmitted($claim));
                $notifiedUserIds[] = $deptApprover->approver->user->id;
            }
        }

        // Also notify HR admins not already notified
        User::where('role', 'admin')->whereNotIn('id', $notifiedUserIds)->each(function ($admin) use ($claim) {
            $admin->notify(new \App\Notifications\Hr\OvertimeClaimSubmitted($claim));
        });

        return response()->json([
            'data' => $claim,
            'message' => 'OT claim request submitted successfully.',
        ], 201);
    }

    /**
     * Cancel a pending OT claim request.
     */
    public function cancelOvertimeClaim(Request $request, OvertimeClaimRequest $overtimeClaimRequest): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        if ($overtimeClaimRequest->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeClaimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending claims can be cancelled.'], 422);
        }

        $overtimeClaimRequest->update(['status' => 'cancelled']);

        return response()->json(['message' => 'OT claim cancelled.']);
    }

    /**
     * Cancel a pending overtime request.
     */
    public function cancelOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        if ($overtimeRequest->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be cancelled.'], 422);
        }

        $overtimeRequest->update(['status' => 'cancelled']);

        return response()->json([
            'data' => $overtimeRequest->fresh(),
            'message' => 'Overtime request cancelled successfully.',
        ]);
    }

    /**
     * Compute overtime replacement balance for an employee.
     *
     * @return array{total_earned: float, total_used_minutes: int, available_minutes: int}
     */
    private function computeOvertimeBalance(Employee $employee): array
    {
        $totalEarned = (float) OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'completed')
            ->sum('replacement_hours_earned');

        $totalUsedMinutes = (int) OvertimeClaimRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->sum('duration_minutes');

        return [
            'total_earned' => $totalEarned,
            'total_used_minutes' => $totalUsedMinutes,
            'available_minutes' => (int) round(($totalEarned * 60) - $totalUsedMinutes),
        ];
    }

    /**
     * Calculate distance between two GPS coordinates using the Haversine formula.
     *
     * @return float Distance in meters
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
