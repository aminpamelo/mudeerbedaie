<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrEmployeeScheduleController extends Controller
{
    /**
     * List all schedule assignments with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeSchedule::query()
            ->with(['employee.department', 'workSchedule']);

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($workScheduleId = $request->get('work_schedule_id')) {
            $query->where('work_schedule_id', $workScheduleId);
        }

        $perPage = min((int) $request->get('per_page', 15), 500);
        $schedules = $query->orderByDesc('effective_from')->paginate($perPage);

        return response()->json($schedules);
    }

    /**
     * Assign schedule to employee(s) - supports bulk via employee_ids array.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['exists:employees,id'],
            'work_schedule_id' => ['required', 'exists:work_schedules,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'custom_start_time' => ['nullable', 'date_format:H:i'],
            'custom_end_time' => ['nullable', 'date_format:H:i'],
        ]);

        return DB::transaction(function () use ($validated) {
            $created = [];

            foreach ($validated['employee_ids'] as $employeeId) {
                // Deactivate any existing active schedules for this employee
                EmployeeSchedule::where('employee_id', $employeeId)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
                    ->update(['effective_to' => now()->subDay()]);

                $employeeSchedule = EmployeeSchedule::create([
                    'employee_id' => $employeeId,
                    'work_schedule_id' => $validated['work_schedule_id'],
                    'effective_from' => $validated['effective_from'],
                    'effective_to' => $validated['effective_to'] ?? null,
                    'custom_start_time' => $validated['custom_start_time'] ?? null,
                    'custom_end_time' => $validated['custom_end_time'] ?? null,
                ]);

                $employeeSchedule->load('employee.user', 'workSchedule');
                if ($employeeSchedule->employee->user) {
                    $employeeSchedule->employee->user->notify(
                        new \App\Notifications\Hr\ScheduleChanged($employeeSchedule)
                    );
                }

                $created[] = $employeeSchedule;
            }

            return response()->json([
                'data' => $created,
                'message' => count($created).' schedule(s) assigned successfully.',
            ], 201);
        });
    }

    /**
     * Update a schedule assignment.
     */
    public function update(Request $request, EmployeeSchedule $employeeSchedule): JsonResponse
    {
        $validated = $request->validate([
            'work_schedule_id' => ['sometimes', 'exists:work_schedules,id'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'custom_start_time' => ['nullable', 'date_format:H:i'],
            'custom_end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $employeeSchedule->update($validated);

        $employeeSchedule->load('employee.user', 'workSchedule');
        if ($employeeSchedule->employee->user) {
            $employeeSchedule->employee->user->notify(
                new \App\Notifications\Hr\ScheduleChanged($employeeSchedule)
            );
        }

        return response()->json([
            'data' => $employeeSchedule->fresh(['employee', 'workSchedule']),
            'message' => 'Schedule assignment updated successfully.',
        ]);
    }

    /**
     * Remove a schedule assignment.
     */
    public function destroy(EmployeeSchedule $employeeSchedule): JsonResponse
    {
        $employeeSchedule->delete();

        return response()->json(['message' => 'Schedule assignment removed successfully.']);
    }
}
