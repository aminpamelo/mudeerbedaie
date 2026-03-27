<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HrLeaveDashboardController extends Controller
{
    /**
     * Leave dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        $pendingCount = LeaveRequest::query()
            ->where('status', 'pending')
            ->count();

        $approvedThisMonth = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereBetween('approved_at', [$startOfMonth, $endOfMonth])
            ->count();

        $onLeaveToday = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        $upcomingLeaves = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '>', $today)
            ->whereDate('start_date', '<=', $today->copy()->addDays(7))
            ->count();

        return response()->json([
            'data' => [
                'pending_count' => $pendingCount,
                'approved_this_month' => $approvedThisMonth,
                'on_leave_today' => $onLeaveToday,
                'upcoming_leaves' => $upcomingLeaves,
            ],
        ]);
    }

    /**
     * List pending leave requests with employee and leave type info.
     */
    public function pending(): JsonResponse
    {
        $pending = LeaveRequest::query()
            ->with(['employee.department', 'leaveType'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $pending]);
    }

    /**
     * Leave type distribution for the current year.
     */
    public function distribution(): JsonResponse
    {
        $currentYear = now()->year;

        $distribution = LeaveRequest::query()
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->whereYear('leave_requests.start_date', $currentYear)
            ->whereIn('leave_requests.status', ['approved', 'pending'])
            ->select(
                'leave_types.id',
                'leave_types.name',
                'leave_types.color',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(leave_requests.total_days) as total_days')
            )
            ->groupBy('leave_types.id', 'leave_types.name', 'leave_types.color')
            ->orderByDesc('request_count')
            ->get();

        return response()->json(['data' => $distribution]);
    }
}
