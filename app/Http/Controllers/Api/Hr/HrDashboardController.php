<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use Illuminate\Http\JsonResponse;

class HrDashboardController extends Controller
{
    /**
     * Get HR dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('status', 'active')->count();
        $newHiresThisMonth = Employee::whereMonth('join_date', now()->month)
            ->whereYear('join_date', now()->year)
            ->count();
        $onProbation = Employee::where('status', 'probation')->count();
        $departmentsCount = Department::count();

        return response()->json([
            'data' => [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'new_hires_this_month' => $newHiresThisMonth,
                'on_probation' => $onProbation,
                'departments_count' => $departmentsCount,
            ],
        ]);
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
