<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrTrainingEnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enrollments = TrainingEnrollment::query()
            ->with([
                'employee:id,full_name,employee_id',
                'trainingProgram:id,title,start_date,end_date',
            ])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json($enrollments);
    }

    public function enroll(Request $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        return DB::transaction(function () use ($validated, $trainingProgram, $request) {
            $enrolled = [];

            foreach ($validated['employee_ids'] as $employeeId) {
                $existing = TrainingEnrollment::where('training_program_id', $trainingProgram->id)
                    ->where('employee_id', $employeeId)
                    ->first();

                if (! $existing) {
                    $enrolled[] = TrainingEnrollment::create([
                        'training_program_id' => $trainingProgram->id,
                        'employee_id' => $employeeId,
                        'enrolled_by' => $request->user()->id,
                        'status' => 'enrolled',
                    ]);
                }
            }

            return response()->json([
                'message' => count($enrolled).' employee(s) enrolled.',
                'data' => $enrolled,
            ], 201);
        });
    }

    public function update(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:enrolled,attended,absent,cancelled'],
        ]);

        $trainingEnrollment->update(array_merge($validated, [
            'attendance_confirmed_at' => in_array($validated['status'], ['attended', 'absent']) ? now() : null,
        ]));

        return response()->json([
            'message' => 'Enrollment updated.',
            'data' => $trainingEnrollment,
        ]);
    }

    public function destroy(TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $trainingEnrollment->delete();

        return response()->json(['message' => 'Enrollment cancelled.']);
    }

    public function feedback(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
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
