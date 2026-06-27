<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeAdjustment;
use App\Models\OvertimeClaimRequest;
use App\Models\OvertimeRequest;
use App\Services\Hr\OvertimeBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HrOvertimeController extends Controller
{
    /**
     * List all overtime requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OvertimeRequest::query()
            ->with(['employee.department']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('requested_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('requested_date', '<=', $dateTo);
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $requests = $query->orderByDesc('requested_date')->paginate($perPage);

        return response()->json($requests);
    }

    /**
     * System-wide overtime overview: headline totals, status breakdown,
     * per-department hours, and a 6-month trend, scoped by period + department.
     */
    public function overview(Request $request): JsonResponse
    {
        [$from, $to, $period] = $this->resolvePeriod($request);
        $departmentId = $request->get('department_id');

        $statusCounts = $this->filteredQuery($from, $to, $departmentId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statusBreakdown = collect(['pending', 'approved', 'completed', 'rejected', 'cancelled'])
            ->mapWithKeys(fn ($s) => [$s => (int) ($statusCounts[$s] ?? 0)]);

        $completedHours = (float) $this->filteredQuery($from, $to, $departmentId)
            ->where('status', 'completed')->sum('actual_hours');
        $pendingHours = (float) $this->filteredQuery($from, $to, $departmentId)
            ->where('status', 'pending')->sum('estimated_hours');
        $employeesOnOt = (int) $this->filteredQuery($from, $to, $departmentId)
            ->distinct('employee_id')->count('employee_id');

        // Period-scoped adjustment total feeds the "Total OT Hours" headline.
        $adjustmentTotal = (float) $this->filteredAdjustments($from, $to, $departmentId)->sum('minutes') / 60;

        // The replacement balance is a running, cumulative figure (a bank
        // balance), so it is computed all-time — never period-scoped — to match
        // the "available" balance each employee sees on their own dashboard.
        $replacement = $this->allTimeReplacement($departmentId);

        $byDepartment = $this->filteredQuery($from, $to, $departmentId)
            ->where('overtime_requests.status', 'completed')
            ->join('employees', 'overtime_requests.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('hours')
            ->get([
                'departments.id',
                'departments.name',
                DB::raw('ROUND(SUM(overtime_requests.actual_hours), 1) as hours'),
                DB::raw('COUNT(*) as requests'),
            ]);

        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonthsNoOverflow($i);
            $monthQuery = OvertimeRequest::query()
                ->where('status', 'completed')
                ->whereYear('requested_date', $month->year)
                ->whereMonth('requested_date', $month->month);

            if ($departmentId) {
                $monthQuery->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
            }

            $trend[] = [
                'label' => $month->format('M'),
                'hours' => round((float) $monthQuery->sum('actual_hours'), 1),
            ];
        }

        return response()->json([
            'data' => [
                'period' => $period,
                'stats' => [
                    'total_requests' => $statusBreakdown->sum(),
                    'total_ot_hours' => round($completedHours + $adjustmentTotal, 1),
                    'adjustment_total' => round($adjustmentTotal, 1),
                    'pending_count' => $statusBreakdown['pending'],
                    'pending_hours' => round($pendingHours, 1),
                    'completed_count' => $statusBreakdown['completed'],
                    'employees_on_ot' => $employeesOnOt,
                    'replacement_earned' => $replacement['earned_hours'],
                    'replacement_used' => $replacement['used_hours'],
                    'replacement_balance' => $replacement['balance_hours'],
                ],
                'status_breakdown' => $statusBreakdown,
                'by_department' => $byDepartment,
                'trend' => $trend,
                'departments' => Department::orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * Per-employee overtime aggregation for the overview table.
     */
    public function byEmployee(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $departmentId = $request->get('department_id');

        $aggregates = $this->filteredQuery($from, $to, $departmentId)
            ->groupBy('employee_id')
            ->get([
                'employee_id',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count"),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN actual_hours ELSE 0 END) as completed_hours"),
                DB::raw('MAX(requested_date) as last_date'),
            ])
            ->keyBy('employee_id');

        // Signed admin adjustments per employee within the same window.
        $adjustments = $this->filteredAdjustments($from, $to, $departmentId)
            ->groupBy('employee_id')
            ->get([
                'employee_id',
                DB::raw('SUM(minutes) as adjustment_minutes'),
                DB::raw('MAX(effective_date) as last_adjustment'),
            ])
            ->keyBy('employee_id');

        $employeeIds = $aggregates->keys()->merge($adjustments->keys())->unique();

        $employees = Employee::with('department')
            ->whereIn('id', $employeeIds)
            ->get()
            ->keyBy('id');

        // Running replacement balance (all-time, all channels) per employee, so
        // each row's "Repl. Balance" equals the employee's own "available".
        $balances = app(OvertimeBalanceService::class)->forEmployees($employeeIds->all());

        $rows = $employeeIds->map(function ($employeeId) use ($aggregates, $adjustments, $employees, $balances) {
            $agg = $aggregates->get($employeeId);
            $adj = $adjustments->get($employeeId);
            $employee = $employees->get($employeeId);

            $completedHours = round((float) ($agg->completed_hours ?? 0), 1);
            $adjustmentHours = round((int) ($adj->adjustment_minutes ?? 0) / 60, 1);
            $availableMinutes = $balances[$employeeId]['available_minutes'] ?? 0;

            return [
                'employee_id' => $employeeId,
                'full_name' => $employee?->full_name ?? 'Unknown',
                'department' => $employee?->department?->name,
                'total_requests' => (int) ($agg->total_requests ?? 0),
                'pending_count' => (int) ($agg->pending_count ?? 0),
                'completed_hours' => $completedHours,
                'adjustment_hours' => $adjustmentHours,
                'adjustment_minutes' => (int) ($adj->adjustment_minutes ?? 0),
                'ot_hours' => round($completedHours + $adjustmentHours, 1),
                'replacement_balance' => round($availableMinutes / 60, 1),
                'last_date' => $agg->last_date ?? ($adj->last_adjustment ?? null),
            ];
        })->values();

        if ($search = trim((string) $request->get('search'))) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['full_name']), $needle));
        }

        $rows = $rows->sortByDesc('ot_hours')->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * Manually adjust an overtime request's recorded hours (HR override).
     * Keeps an audit trail of who changed it, when, and why.
     */
    public function adjust(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        if (! in_array($overtimeRequest->status, ['approved', 'completed'], true)) {
            return response()->json([
                'message' => 'Only approved or completed overtime can be adjusted.',
            ], 422);
        }

        $validated = $request->validate([
            'actual_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'adjustment_reason' => ['required', 'string', 'min:3'],
        ]);

        $overtimeRequest->update([
            'status' => 'completed',
            'actual_hours' => $validated['actual_hours'],
            'replacement_hours_earned' => $validated['actual_hours'],
            'adjusted_by' => $request->user()->id,
            'adjusted_at' => now(),
            'adjustment_reason' => $validated['adjustment_reason'],
        ]);

        return response()->json([
            'data' => $overtimeRequest->fresh(['employee.department']),
            'message' => 'Overtime adjusted successfully.',
        ]);
    }

    /**
     * List the standalone admin OT adjustments for one employee (full history).
     */
    public function adjustments(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => ['required', 'exists:employees,id']]);

        $adjustments = OvertimeAdjustment::query()
            ->with('adjuster:id,name')
            ->where('employee_id', $request->get('employee_id'))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $adjustments]);
    }

    /**
     * Record a standalone admin adjustment that adds (+) or deducts (-) OT
     * hours for a staff member, independent of any single overtime request.
     */
    public function storeAdjustment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'minutes' => ['required', 'integer', 'not_in:0', 'min:-1440', 'max:1440'],
            'reason' => ['required', 'string', 'min:3'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $adjustment = OvertimeAdjustment::create([
            'employee_id' => $validated['employee_id'],
            'minutes' => $validated['minutes'],
            'reason' => $validated['reason'],
            'effective_date' => $validated['effective_date'] ?? now()->toDateString(),
            'adjusted_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $adjustment->load('adjuster:id,name'),
            'message' => 'Adjustment recorded successfully.',
        ], 201);
    }

    /**
     * Remove an admin OT adjustment.
     */
    public function destroyAdjustment(OvertimeAdjustment $overtimeAdjustment): JsonResponse
    {
        $overtimeAdjustment->delete();

        return response()->json(['message' => 'Adjustment removed.']);
    }

    /**
     * Resolve the requested reporting window.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = $request->get('period', 'this_month');

        return match ($period) {
            'last_month' => [
                Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                Carbon::now()->subMonthNoOverflow()->endOfMonth(),
                'last_month',
            ],
            'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear(), 'this_year'],
            'all' => [null, null, 'all'],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), 'this_month'],
        };
    }

    /**
     * Base overtime query scoped to a date window and optional department.
     */
    private function filteredQuery(?Carbon $from, ?Carbon $to, $departmentId): Builder
    {
        $query = OvertimeRequest::query();

        if ($from) {
            $query->whereDate('requested_date', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('requested_date', '<=', $to->toDateString());
        }

        if ($departmentId) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        return $query;
    }

    /**
     * Admin OT adjustments scoped to a date window and optional department.
     */
    private function filteredAdjustments(?Carbon $from, ?Carbon $to, $departmentId): Builder
    {
        $query = OvertimeAdjustment::query();

        if ($from) {
            $query->whereDate('effective_date', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('effective_date', '<=', $to->toDateString());
        }

        if ($departmentId) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        return $query;
    }

    /**
     * All-time replacement balance summary, optionally scoped to a department.
     * Mirrors OvertimeBalanceService but aggregated across every employee in
     * scope, for the overview headline card.
     *
     * @return array{earned_hours: float, used_hours: float, balance_hours: float}
     */
    private function allTimeReplacement($departmentId): array
    {
        $scopeDept = fn (Builder $query): Builder => $departmentId
            ? $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId))
            : $query;

        $earnedMinutes = (int) round((float) $scopeDept(
            OvertimeRequest::query()->where('status', 'completed')
        )->sum('replacement_hours_earned') * 60);

        $leaveUsedMinutes = (int) round((float) $scopeDept(
            OvertimeRequest::query()
        )->sum('replacement_hours_used') * 60);

        $claimUsedMinutes = (int) $scopeDept(
            OvertimeClaimRequest::query()->where('status', 'approved')
        )->sum('duration_minutes');

        $adjustmentMinutes = (int) $scopeDept(
            OvertimeAdjustment::query()
        )->sum('minutes');

        $usedMinutes = $claimUsedMinutes + $leaveUsedMinutes;
        $availableMinutes = $earnedMinutes + $adjustmentMinutes - $usedMinutes;

        return [
            'earned_hours' => round($earnedMinutes / 60, 1),
            'used_hours' => round($usedMinutes / 60, 1),
            'balance_hours' => round($availableMinutes / 60, 1),
        ];
    }

    /**
     * Show a single overtime request with employee.
     */
    public function show(OvertimeRequest $overtimeRequest): JsonResponse
    {
        $overtimeRequest->load('employee.department');

        return response()->json(['data' => $overtimeRequest]);
    }

    /**
     * Approve an overtime request.
     */
    public function approve(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $overtimeRequest->update([
            'status' => 'completed',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'actual_hours' => $overtimeRequest->estimated_hours,
            'replacement_hours_earned' => $overtimeRequest->estimated_hours,
        ]);

        $overtimeRequest->load('employee.user');
        if ($overtimeRequest->employee->user) {
            $overtimeRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest, 'approved')
            );
        }

        return response()->json([
            'data' => $overtimeRequest->fresh('employee'),
            'message' => 'Overtime request approved successfully.',
        ]);
    }

    /**
     * Reject an overtime request.
     */
    public function reject(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $overtimeRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $overtimeRequest->load('employee.user');
        if ($overtimeRequest->employee->user) {
            $overtimeRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest, 'rejected')
            );
        }

        return response()->json([
            'data' => $overtimeRequest->fresh('employee'),
            'message' => 'Overtime request rejected.',
        ]);
    }

    /**
     * List all OT claim requests (HR admin view).
     */
    public function claims(Request $request): JsonResponse
    {
        $query = OvertimeClaimRequest::query()
            ->with(['employee.department']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('claim_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('claim_date', '<=', $dateTo);
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));

        return response()->json($query->orderByDesc('claim_date')->paginate($perPage));
    }

    /**
     * Approve an OT claim request (HR admin).
     */
    public function approveClaim(Request $request, OvertimeClaimRequest $overtimeClaimRequest): JsonResponse
    {
        if ($overtimeClaimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending claims can be approved.'], 422);
        }

        $overtimeClaimRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $attendanceLog = AttendanceLog::where('employee_id', $overtimeClaimRequest->employee_id)
            ->where('date', $overtimeClaimRequest->claim_date)
            ->first();

        if ($attendanceLog) {
            $newLateMinutes = max(0, (int) $attendanceLog->late_minutes - $overtimeClaimRequest->duration_minutes);
            $newStatus = ($newLateMinutes === 0 && $attendanceLog->status === 'late') ? 'present' : $attendanceLog->status;

            $attendanceLog->update([
                'ot_claim_id' => $overtimeClaimRequest->id,
                'late_minutes' => $newLateMinutes,
                'status' => $newStatus,
            ]);

            $overtimeClaimRequest->update(['attendance_id' => $attendanceLog->id]);
        }

        $overtimeClaimRequest->load('employee.user');
        if ($overtimeClaimRequest->employee->user) {
            $overtimeClaimRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeClaimDecision($overtimeClaimRequest, 'approved')
            );
        }

        return response()->json([
            'data' => $overtimeClaimRequest->fresh(['employee.department']),
            'message' => 'OT claim approved successfully.',
        ]);
    }

    /**
     * Reject an OT claim request (HR admin).
     */
    public function rejectClaim(Request $request, OvertimeClaimRequest $overtimeClaimRequest): JsonResponse
    {
        if ($overtimeClaimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending claims can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $overtimeClaimRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $overtimeClaimRequest->load('employee.user');
        if ($overtimeClaimRequest->employee->user) {
            $overtimeClaimRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeClaimDecision($overtimeClaimRequest, 'rejected')
            );
        }

        return response()->json([
            'data' => $overtimeClaimRequest->fresh(['employee.department']),
            'message' => 'OT claim rejected.',
        ]);
    }

    /**
     * Complete an overtime request with actual hours.
     */
    public function complete(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        if ($overtimeRequest->status !== 'approved') {
            return response()->json(['message' => 'Only approved requests can be completed.'], 422);
        }

        $validated = $request->validate([
            'actual_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
        ]);

        $overtimeRequest->update([
            'status' => 'completed',
            'actual_hours' => $validated['actual_hours'],
            'replacement_hours_earned' => $validated['actual_hours'],
        ]);

        return response()->json([
            'data' => $overtimeRequest->fresh('employee'),
            'message' => 'Overtime request completed successfully.',
        ]);
    }
}
