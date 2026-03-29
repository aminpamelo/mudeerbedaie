<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiTemplate;
use App\Models\PerformanceReview;
use App\Models\ReviewCycle;
use App\Models\ReviewKpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrReviewCycleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cycles = ReviewCycle::query()
            ->withCount('reviews')
            ->orderByDesc('start_date')
            ->paginate($request->get('per_page', 15));

        return response()->json($cycles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:monthly,quarterly,semi_annual,annual'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'submission_deadline' => ['required', 'date', 'after:end_date'],
            'description' => ['nullable', 'string'],
        ]);

        $cycle = ReviewCycle::create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Review cycle created.',
            'data' => $cycle,
        ], 201);
    }

    public function show(ReviewCycle $reviewCycle): JsonResponse
    {
        return response()->json([
            'data' => $reviewCycle->load([
                'reviews' => fn ($q) => $q->with([
                    'employee:id,full_name,employee_id,department_id,position_id',
                    'employee.department:id,name',
                    'reviewer:id,full_name',
                ]),
            ])->loadCount('reviews'),
        ]);
    }

    public function update(Request $request, ReviewCycle $reviewCycle): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:monthly,quarterly,semi_annual,annual'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'submission_deadline' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $reviewCycle->update($validated);

        return response()->json([
            'message' => 'Review cycle updated.',
            'data' => $reviewCycle,
        ]);
    }

    public function destroy(ReviewCycle $reviewCycle): JsonResponse
    {
        if ($reviewCycle->status !== 'draft') {
            return response()->json(['message' => 'Only draft cycles can be deleted.'], 422);
        }

        $reviewCycle->delete();

        return response()->json(['message' => 'Review cycle deleted.']);
    }

    public function activate(ReviewCycle $reviewCycle): JsonResponse
    {
        return DB::transaction(function () use ($reviewCycle) {
            $reviewCycle->update(['status' => 'active']);

            // Auto-create reviews for all active employees
            $employees = Employee::where('status', 'active')->get();

            foreach ($employees as $employee) {
                // Find the employee's manager (department head or any admin for now)
                $reviewer = Employee::where('department_id', $employee->department_id)
                    ->where('id', '!=', $employee->id)
                    ->first() ?? $employee;

                $review = PerformanceReview::firstOrCreate(
                    [
                        'review_cycle_id' => $reviewCycle->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'reviewer_id' => $reviewer->id,
                        'status' => 'draft',
                    ]
                );

                // Auto-assign KPIs from templates matching position/department
                $kpiTemplates = KpiTemplate::where('is_active', true)
                    ->where(function ($q) use ($employee) {
                        $q->where('position_id', $employee->position_id)
                            ->orWhere('department_id', $employee->department_id)
                            ->orWhere(function ($q2) {
                                $q2->whereNull('position_id')->whereNull('department_id');
                            });
                    })
                    ->get();

                foreach ($kpiTemplates as $template) {
                    ReviewKpi::firstOrCreate(
                        [
                            'performance_review_id' => $review->id,
                            'kpi_template_id' => $template->id,
                        ],
                        [
                            'title' => $template->title,
                            'target' => $template->target,
                            'weight' => $template->weight,
                        ]
                    );
                }
            }

            return response()->json([
                'message' => 'Review cycle activated. Reviews created for '.count($employees).' employees.',
                'data' => $reviewCycle->fresh()->loadCount('reviews'),
            ]);
        });
    }

    public function complete(ReviewCycle $reviewCycle): JsonResponse
    {
        $reviewCycle->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Review cycle marked as completed.',
            'data' => $reviewCycle,
        ]);
    }
}
