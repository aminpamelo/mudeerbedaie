<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\PerformanceReview;
use App\Models\RatingScale;
use App\Models\ReviewKpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPerformanceReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PerformanceReview::query()
            ->with([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'reviewer:id,full_name',
                'reviewCycle:id,name',
            ]);

        if ($cycleId = $request->get('review_cycle_id')) {
            $query->where('review_cycle_id', $cycleId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $reviews = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($reviews);
    }

    public function show(PerformanceReview $performanceReview): JsonResponse
    {
        return response()->json([
            'data' => $performanceReview->load([
                'employee:id,full_name,employee_id,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,title',
                'reviewer:id,full_name',
                'reviewCycle:id,name,type,start_date,end_date',
                'kpis',
            ]),
        ]);
    }

    public function addKpi(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:1', 'max:100'],
            'kpi_template_id' => ['nullable', 'exists:kpi_templates,id'],
        ]);

        $kpi = ReviewKpi::create(array_merge($validated, [
            'performance_review_id' => $performanceReview->id,
        ]));

        return response()->json([
            'message' => 'KPI added to review.',
            'data' => $kpi,
        ], 201);
    }

    public function selfAssessment(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
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
                ReviewKpi::where('id', $kpiData['id'])->update([
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

    public function managerReview(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $validated = $request->validate([
            'manager_notes' => ['nullable', 'string'],
            'kpis' => ['required', 'array'],
            'kpis.*.id' => ['required', 'exists:review_kpis,id'],
            'kpis.*.manager_score' => ['required', 'integer', 'min:1', 'max:5'],
            'kpis.*.manager_comments' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $performanceReview) {
            foreach ($validated['kpis'] as $kpiData) {
                ReviewKpi::where('id', $kpiData['id'])->update([
                    'manager_score' => $kpiData['manager_score'],
                    'manager_comments' => $kpiData['manager_comments'] ?? null,
                ]);
            }

            $performanceReview->update([
                'manager_notes' => $validated['manager_notes'],
                'status' => 'manager_review',
            ]);

            return response()->json([
                'message' => 'Manager review submitted.',
                'data' => $performanceReview->fresh('kpis'),
            ]);
        });
    }

    public function complete(PerformanceReview $performanceReview): JsonResponse
    {
        $overallRating = $performanceReview->calculateOverallRating();
        $ratingLabel = null;

        if ($overallRating) {
            $ratingScale = RatingScale::where('score', round($overallRating))->first();
            $ratingLabel = $ratingScale?->label;
        }

        $performanceReview->update([
            'overall_rating' => $overallRating,
            'rating_label' => $ratingLabel,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Review completed.',
            'data' => $performanceReview,
        ]);
    }

    public function acknowledge(PerformanceReview $performanceReview): JsonResponse
    {
        $performanceReview->update([
            'employee_acknowledged' => true,
            'acknowledged_at' => now(),
        ]);

        return response()->json([
            'message' => 'Review acknowledged.',
            'data' => $performanceReview,
        ]);
    }
}
