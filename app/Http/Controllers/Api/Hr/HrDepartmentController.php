<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDepartmentRequest;
use App\Http\Requests\Hr\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrDepartmentController extends Controller
{
    /**
     * List all departments with employee count.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::query()->with('parent:id,name')->withCount('employees');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $departments = $query->orderBy('name')->get();

        return response()->json(['data' => $departments]);
    }

    /**
     * Create a department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return response()->json([
            'data' => $department,
            'message' => 'Department created successfully.',
        ], 201);
    }

    /**
     * Show department with positions and employee count.
     */
    public function show(Department $department): JsonResponse
    {
        $department->load('positions')->loadCount('employees');

        return response()->json(['data' => $department]);
    }

    /**
     * Update a department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());

        return response()->json([
            'data' => $department,
            'message' => 'Department updated successfully.',
        ]);
    }

    /**
     * Delete a department (only if no employees assigned).
     */
    public function destroy(Department $department): JsonResponse
    {
        if ($department->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete department with assigned employees.',
            ], 422);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    /**
     * Return hierarchical department tree.
     */
    public function tree(): JsonResponse
    {
        $departments = Department::with('children.children')
            ->whereNull('parent_id')
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $departments]);
    }

    /**
     * List employees in a department.
     */
    public function employees(Department $department): JsonResponse
    {
        $employees = $department->employees()
            ->with('position:id,title')
            ->orderBy('full_name')
            ->get();

        return response()->json(['data' => $employees]);
    }
}
