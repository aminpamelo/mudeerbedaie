<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreWorkScheduleRequest;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HrWorkScheduleController extends Controller
{
    /**
     * List all work schedules with employee count.
     */
    public function index(): JsonResponse
    {
        $schedules = WorkSchedule::query()
            ->withCount(['employeeSchedules' => function ($query) {
                $query->where('effective_from', '<=', now())
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
                    ->whereHas('employee', fn ($q) => $q->whereNotIn('status', ['terminated', 'resigned']));
            }])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $schedules]);
    }

    /**
     * Create a new work schedule.
     */
    public function store(StoreWorkScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            if (! empty($validated['is_default'])) {
                WorkSchedule::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $schedule = WorkSchedule::create($validated);

            return response()->json([
                'data' => $schedule,
                'message' => 'Work schedule created successfully.',
            ], 201);
        });
    }

    /**
     * Show a single work schedule with employee schedules.
     */
    public function show(WorkSchedule $workSchedule): JsonResponse
    {
        $workSchedule->load('employeeSchedules.employee');

        return response()->json(['data' => $workSchedule]);
    }

    /**
     * Update a work schedule.
     */
    public function update(StoreWorkScheduleRequest $request, WorkSchedule $workSchedule): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $workSchedule) {
            if (! empty($validated['is_default'])) {
                WorkSchedule::query()
                    ->where('is_default', true)
                    ->where('id', '!=', $workSchedule->id)
                    ->update(['is_default' => false]);
            }

            $workSchedule->update($validated);

            return response()->json([
                'data' => $workSchedule->fresh(),
                'message' => 'Work schedule updated successfully.',
            ]);
        });
    }

    /**
     * Delete a work schedule if no employees are assigned.
     */
    public function destroy(WorkSchedule $workSchedule): JsonResponse
    {
        if ($workSchedule->employeeSchedules()->exists()) {
            return response()->json([
                'message' => 'Cannot delete schedule with assigned employees.',
            ], 422);
        }

        $workSchedule->delete();

        return response()->json(['message' => 'Work schedule deleted successfully.']);
    }

    /**
     * List employees on this schedule.
     */
    public function employees(WorkSchedule $workSchedule): JsonResponse
    {
        $employees = $workSchedule->employeeSchedules()
            ->with('employee.department')
            ->get();

        return response()->json(['data' => $employees]);
    }
}
