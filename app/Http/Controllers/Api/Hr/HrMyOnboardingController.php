<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OnboardingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyOnboardingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $tasks = OnboardingTask::where('employee_id', $employee->id)
            ->with('assignedEmployee:id,full_name')
            ->orderBy('due_date')
            ->get();

        $total = $tasks->count();
        $completed = $tasks->where('status', 'completed')->count();

        return response()->json([
            'data' => [
                'tasks' => $tasks,
                'progress' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'total' => $total,
                'completed' => $completed,
            ],
        ]);
    }
}
