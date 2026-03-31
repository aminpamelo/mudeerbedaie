<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePositionRequest;
use App\Http\Requests\Hr\UpdatePositionRequest;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPositionController extends Controller
{
    /**
     * List positions with department and employee count.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::query()
            ->with('department:id,name')
            ->withCount('employees');

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($search = $request->get('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $positions = $query->orderBy('title')->get();

        return response()->json(['data' => $positions]);
    }

    /**
     * Create a position.
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());
        $position->load('department:id,name');

        return response()->json([
            'data' => $position,
            'message' => 'Position created successfully.',
        ], 201);
    }

    /**
     * Show a position with department and assigned employees.
     */
    public function show(Position $position): JsonResponse
    {
        $position->load(['department:id,name', 'employees:employees.id,employees.full_name,employees.employee_id,employees.department_id,employees.profile_photo'])
            ->loadCount('employees');

        return response()->json(['data' => $position]);
    }

    /**
     * Update a position.
     */
    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $position->update($request->validated());
        $position->load('department:id,name');

        return response()->json([
            'data' => $position,
            'message' => 'Position updated successfully.',
        ]);
    }

    /**
     * Delete a position (only if no employees assigned).
     */
    public function destroy(Position $position): JsonResponse
    {
        if ($position->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete position with assigned employees.',
            ], 422);
        }

        $position->delete();

        return response()->json(['message' => 'Position deleted successfully.']);
    }

    /**
     * Get employees assigned to a position.
     */
    public function employees(Position $position): JsonResponse
    {
        $employees = $position->employees()
            ->select('employees.id', 'employees.full_name', 'employees.employee_id', 'employees.department_id', 'employees.profile_photo')
            ->with('department:id,name')
            ->get();

        return response()->json(['data' => $employees]);
    }

    /**
     * Assign employee(s) to a position.
     */
    public function assignEmployees(Request $request, Position $position): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|integer|exists:employees,id',
        ]);

        foreach ($validated['employee_ids'] as $employeeId) {
            $position->employees()->syncWithoutDetaching([
                $employeeId => ['is_primary' => false],
            ]);
        }

        $position->loadCount('employees');
        $position->load(['employees:employees.id,full_name,employee_id,department_id,profile_photo']);

        return response()->json([
            'data' => $position,
            'message' => 'Employee(s) assigned successfully.',
        ]);
    }

    /**
     * Remove an employee from a position.
     */
    public function removeEmployee(Position $position, Employee $employee): JsonResponse
    {
        $position->employees()->detach($employee->id);
        $position->loadCount('employees');

        return response()->json([
            'data' => $position,
            'message' => 'Employee removed from position successfully.',
        ]);
    }
}
