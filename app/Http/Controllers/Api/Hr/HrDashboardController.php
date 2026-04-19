<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\ClaimRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class HrDashboardController extends Controller
{
    /**
     * Get HR dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $today = Carbon::today();

        $totalEmployees = Employee::count();
        $activeEmployees = Employee::whereIn('status', ['active', 'probation'])->count();
        $newHiresThisMonth = Employee::whereMonth('join_date', $today->month)
            ->whereYear('join_date', $today->year)
            ->count();
        $onProbation = Employee::where('status', 'probation')->count();
        $departmentsCount = Department::count();

        // Employment type breakdown
        $employees = Employee::whereIn('status', ['active', 'probation'])->get(['employment_type']);
        $employmentBreakdown = ['full_time' => 0, 'part_time' => 0, 'contract' => 0, 'intern' => 0];
        foreach ($employees as $emp) {
            $types = is_array($emp->employment_type) ? $emp->employment_type : [$emp->employment_type];
            foreach ($types as $type) {
                if (isset($employmentBreakdown[$type])) {
                    $employmentBreakdown[$type]++;
                }
            }
        }

        // Probation ending soon (within 30 days)
        $probationEndingSoon = Employee::where('status', 'probation')
            ->whereNotNull('probation_end_date')
            ->whereDate('probation_end_date', '<=', $today->copy()->addDays(30))
            ->with(['position:id,title', 'department:id,name'])
            ->orderBy('probation_end_date')
            ->get(['id', 'full_name', 'employee_id', 'position_id', 'department_id', 'probation_end_date']);

        return response()->json([
            'data' => [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'new_hires_this_month' => $newHiresThisMonth,
                'on_probation' => $onProbation,
                'departments_count' => $departmentsCount,
                'employment_type_breakdown' => $employmentBreakdown,
                'probation_ending_soon' => $probationEndingSoon,
            ],
        ]);
    }

    /**
     * Get today's attendance summary for the dashboard.
     */
    public function todayAttendance(): JsonResponse
    {
        $today = Carbon::today();

        $activeEmployees = Employee::whereIn('status', ['active', 'probation'])->count();

        $logs = AttendanceLog::query()
            ->whereDate('date', $today)
            ->get();

        $present = $logs->whereIn('status', ['present', 'wfh'])->count();
        $late = $logs->where('status', 'late')->count();
        $earlyLeave = $logs->where('status', 'early_leave')->count();
        $wfh = $logs->where('status', 'wfh')->count();
        $clockedIn = $logs->count();

        // On leave today
        $onLeaveToday = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        $notClockedIn = max(0, $activeEmployees - $clockedIn - $onLeaveToday);

        // Recent clock-ins (last 8)
        $recentClockIns = AttendanceLog::query()
            ->with(['employee:id,full_name,employee_id,profile_photo,department_id', 'employee.department:id,name'])
            ->whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->orderByDesc('clock_in')
            ->limit(8)
            ->get(['id', 'employee_id', 'clock_in', 'status']);

        return response()->json([
            'data' => [
                'total_active' => $activeEmployees,
                'clocked_in' => $clockedIn,
                'present' => $present,
                'late' => $late,
                'early_leave' => $earlyLeave,
                'wfh' => $wfh,
                'on_leave' => $onLeaveToday,
                'not_clocked_in' => $notClockedIn,
                'attendance_rate' => $activeEmployees > 0
                    ? round(($clockedIn / $activeEmployees) * 100, 1)
                    : 0,
                'recent_clock_ins' => $recentClockIns,
            ],
        ]);
    }

    /**
     * Get pending approvals summary for dashboard.
     */
    public function pendingApprovals(): JsonResponse
    {
        $pendingLeave = LeaveRequest::where('status', 'pending')->count();

        $pendingOvertime = OvertimeRequest::where('status', 'pending')->count();

        $pendingClaims = ClaimRequest::where('status', 'pending')->count();

        // Get the latest 5 pending items across all types
        $latestPending = collect();

        $recentLeaves = LeaveRequest::query()
            ->with(['employee:id,full_name,employee_id,profile_photo', 'leaveType:id,name'])
            ->where('status', 'pending')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'leave',
                'label' => $r->leaveType?->name ?? 'Leave',
                'employee_name' => $r->employee?->full_name,
                'employee_photo' => $r->employee?->profile_photo_url,
                'detail' => $r->start_date->format('d M').($r->total_days > 1 ? ' - '.$r->end_date->format('d M') : '').' ('.$r->total_days.'d)',
                'created_at' => $r->created_at,
            ]);

        $recentOT = OvertimeRequest::query()
            ->with(['employee:id,full_name,employee_id,profile_photo'])
            ->where('status', 'pending')
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'overtime',
                'label' => 'Overtime',
                'employee_name' => $r->employee?->full_name,
                'employee_photo' => $r->employee?->profile_photo_url,
                'detail' => $r->requested_date->format('d M').' ('.$r->estimated_hours.'h)',
                'created_at' => $r->created_at,
            ]);

        $recentClaims = ClaimRequest::query()
            ->with(['employee:id,full_name,employee_id,profile_photo', 'claimType:id,name'])
            ->where('status', 'pending')
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'claim',
                'label' => $r->claimType?->name ?? 'Claim',
                'employee_name' => $r->employee?->full_name,
                'employee_photo' => $r->employee?->profile_photo_url,
                'detail' => 'RM '.number_format($r->amount, 2),
                'created_at' => $r->created_at,
            ]);

        $latestPending = $recentLeaves->concat($recentOT)->concat($recentClaims)
            ->sortByDesc('created_at')
            ->take(6)
            ->values();

        return response()->json([
            'data' => [
                'pending_leave' => $pendingLeave,
                'pending_overtime' => $pendingOvertime,
                'pending_claims' => $pendingClaims,
                'total_pending' => $pendingLeave + $pendingOvertime + $pendingClaims,
                'latest_pending' => $latestPending,
            ],
        ]);
    }

    /**
     * Get employees on leave today with details.
     */
    public function onLeaveToday(): JsonResponse
    {
        $today = Carbon::today();

        $onLeave = LeaveRequest::query()
            ->with(['employee:id,full_name,employee_id,profile_photo,department_id', 'employee.department:id,name', 'leaveType:id,name,color'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'employee_name' => $r->employee?->full_name,
                'employee_photo' => $r->employee?->profile_photo_url,
                'department' => $r->employee?->department?->name,
                'leave_type' => $r->leaveType?->name,
                'leave_color' => $r->leaveType?->color,
                'start_date' => $r->start_date->format('d M'),
                'end_date' => $r->end_date->format('d M'),
                'total_days' => $r->total_days,
                'is_half_day' => $r->is_half_day,
                'half_day_period' => $r->half_day_period,
            ]);

        return response()->json(['data' => $onLeave]);
    }

    /**
     * Get upcoming birthdays and work anniversaries.
     */
    public function upcomingEvents(): JsonResponse
    {
        $today = Carbon::today();
        $endOfRange = $today->copy()->addDays(30);

        // Birthdays in the next 30 days
        $birthdays = Employee::query()
            ->whereIn('status', ['active', 'probation'])
            ->whereNotNull('date_of_birth')
            ->get(['id', 'full_name', 'employee_id', 'date_of_birth', 'profile_photo', 'department_id'])
            ->filter(function ($emp) use ($today, $endOfRange) {
                $birthday = Carbon::parse($emp->date_of_birth)->setYear($today->year);
                if ($birthday->lt($today)) {
                    $birthday->addYear();
                }

                return $birthday->between($today, $endOfRange);
            })
            ->map(function ($emp) use ($today) {
                $birthday = Carbon::parse($emp->date_of_birth)->setYear($today->year);
                if ($birthday->lt($today)) {
                    $birthday->addYear();
                }

                return [
                    'id' => $emp->id,
                    'full_name' => $emp->full_name,
                    'profile_photo' => $emp->profile_photo_url,
                    'event_type' => 'birthday',
                    'date' => $birthday->format('d M'),
                    'days_away' => $today->diffInDays($birthday),
                    'is_today' => $birthday->isToday(),
                ];
            })
            ->sortBy('days_away')
            ->take(8)
            ->values();

        // Work anniversaries in the next 30 days
        $anniversaries = Employee::query()
            ->whereIn('status', ['active', 'probation'])
            ->whereNotNull('join_date')
            ->whereDate('join_date', '<', $today)
            ->get(['id', 'full_name', 'employee_id', 'join_date', 'profile_photo', 'department_id'])
            ->filter(function ($emp) use ($today, $endOfRange) {
                $anniversary = Carbon::parse($emp->join_date)->setYear($today->year);
                if ($anniversary->lt($today)) {
                    $anniversary->addYear();
                }

                return $anniversary->between($today, $endOfRange);
            })
            ->map(function ($emp) use ($today) {
                $anniversary = Carbon::parse($emp->join_date)->setYear($today->year);
                if ($anniversary->lt($today)) {
                    $anniversary->addYear();
                }
                $years = $anniversary->year - Carbon::parse($emp->join_date)->year;

                return [
                    'id' => $emp->id,
                    'full_name' => $emp->full_name,
                    'profile_photo' => $emp->profile_photo_url,
                    'event_type' => 'anniversary',
                    'date' => $anniversary->format('d M'),
                    'days_away' => $today->diffInDays($anniversary),
                    'is_today' => $anniversary->isToday(),
                    'years' => $years,
                ];
            })
            ->sortBy('days_away')
            ->take(5)
            ->values();

        return response()->json([
            'data' => [
                'birthdays' => $birthdays,
                'anniversaries' => $anniversaries,
            ],
        ]);
    }

    /**
     * Get today's meetings.
     */
    public function todayMeetings(): JsonResponse
    {
        $today = Carbon::today();

        $meetings = Meeting::query()
            ->whereDate('meeting_date', $today)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->withCount('attendees')
            ->orderBy('start_time')
            ->limit(5)
            ->get(['id', 'title', 'location', 'meeting_date', 'start_time', 'end_time', 'status']);

        return response()->json(['data' => $meetings]);
    }

    /**
     * Get recent activity (last 10 history entries).
     */
    public function recentActivity(): JsonResponse
    {
        $activities = EmployeeHistory::with(['employee:id,full_name,employee_id', 'changedByUser:id,name'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        return response()->json(['data' => $activities]);
    }

    /**
     * Get headcount grouped by department.
     */
    public function headcountByDepartment(): JsonResponse
    {
        $departments = Department::withCount(['employees' => function ($query) {
            $query->whereIn('status', ['active', 'probation']);
        }])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $dept) => [
                'name' => $dept->name,
                'count' => $dept->employees_count,
            ]);

        return response()->json(['data' => $departments]);
    }
}
