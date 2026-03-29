<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $programsByDepartment = TrainingEnrollment::where('status', 'attended')
            ->whereHas('trainingProgram', fn ($q) => $q->whereYear('start_date', $year))
            ->with(['employee:id,department_id', 'employee.department:id,name', 'trainingProgram:id,start_date,end_date'])
            ->get()
            ->groupBy('employee.department.name')
            ->map(fn ($group) => $group->count());

        $totalCost = TrainingProgram::whereYear('start_date', $year)
            ->withSum('costs', 'amount')
            ->get()
            ->sum('costs_sum_amount');

        $totalEnrollments = TrainingEnrollment::whereHas('trainingProgram', fn ($q) => $q->whereYear('start_date', $year))->count();
        $attendedEnrollments = TrainingEnrollment::where('status', 'attended')
            ->whereHas('trainingProgram', fn ($q) => $q->whereYear('start_date', $year))
            ->count();

        return response()->json([
            'data' => [
                'year' => $year,
                'training_by_department' => $programsByDepartment,
                'total_cost' => round($totalCost, 2),
                'total_enrollments' => $totalEnrollments,
                'attended' => $attendedEnrollments,
                'attendance_rate' => $totalEnrollments > 0
                    ? round(($attendedEnrollments / $totalEnrollments) * 100, 1)
                    : 0,
            ],
        ]);
    }
}
