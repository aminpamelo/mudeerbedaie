<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\TrainingEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyTrainingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $trainings = TrainingEnrollment::where('employee_id', $employee->id)
            ->with(['trainingProgram:id,title,type,category,start_date,end_date,location,status'])
            ->orderByDesc('created_at')
            ->get();

        $certifications = EmployeeCertification::where('employee_id', $employee->id)
            ->with('certification:id,name,issuing_body')
            ->orderByDesc('expiry_date')
            ->get();

        return response()->json([
            'data' => [
                'trainings' => $trainings,
                'certifications' => $certifications,
            ],
        ]);
    }

    public function feedback(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($trainingEnrollment->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'feedback' => ['required', 'string'],
            'feedback_rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $trainingEnrollment->update($validated);

        return response()->json([
            'message' => 'Feedback submitted.',
            'data' => $trainingEnrollment,
        ]);
    }
}
