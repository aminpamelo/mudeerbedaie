<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceReview;
use App\Models\ReviewKpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrMyReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $reviews = PerformanceReview::where('employee_id', $employee->id)
            ->with(['reviewCycle:id,name,type,start_date,end_date', 'reviewer:id,full_name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $reviews]);
    }

    public function show(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($performanceReview->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $performanceReview->load([
                'reviewCycle:id,name,type,start_date,end_date',
                'reviewer:id,full_name',
                'kpis',
            ]),
        ]);
    }

    public function selfAssessment(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($performanceReview->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'self_assessment_notes' => ['nullable', 'string'],
            'kpis' => ['required', 'array'],
            'kpis.*.id' => ['required', 'exists:review_kpis,id'],
            'kpis.*.self_score' => ['required', 'integer', 'min:1', 'max:5'],
            'kpis.*.self_comments' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $performanceReview) {
            $performanceReview->update([
                'self_assessment_notes' => $validated['self_assessment_notes'],
                'status' => 'self_assessment',
            ]);

            foreach ($validated['kpis'] as $kpiData) {
                ReviewKpi::where('id', $kpiData['id'])
                    ->where('performance_review_id', $performanceReview->id)
                    ->update([
                        'self_score' => $kpiData['self_score'],
                        'self_comments' => $kpiData['self_comments'] ?? null,
                    ]);
            }

            return response()->json([
                'message' => 'Self-assessment submitted.',
                'data' => $performanceReview->fresh('kpis'),
            ]);
        });
    }

    public function myPip(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $pip = PerformanceImprovementPlan::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->with('goals')
            ->first();

        return response()->json(['data' => $pip]);
    }
}
