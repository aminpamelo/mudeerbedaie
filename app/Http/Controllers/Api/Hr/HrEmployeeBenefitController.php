<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeBenefitRequest;
use App\Models\EmployeeBenefit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmployeeBenefitController extends Controller
{
    /**
     * List employee benefits with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeBenefit::query()
            ->with(['employee', 'benefitType']);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($benefitTypeId = $request->get('benefit_type_id')) {
            $query->where('benefit_type_id', $benefitTypeId);
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $benefits = $query->orderByDesc('start_date')->get();

        return response()->json(['data' => $benefits]);
    }

    /**
     * Create a new employee benefit record.
     */
    public function store(StoreEmployeeBenefitRequest $request): JsonResponse
    {
        $benefit = EmployeeBenefit::create($request->validated());
        $benefit->load(['employee', 'benefitType']);

        return response()->json([
            'data' => $benefit,
            'message' => 'Employee benefit created successfully.',
        ], 201);
    }

    /**
     * Update an employee benefit record.
     */
    public function update(StoreEmployeeBenefitRequest $request, EmployeeBenefit $employeeBenefit): JsonResponse
    {
        $employeeBenefit->update($request->validated());

        return response()->json([
            'data' => $employeeBenefit->fresh(['employee', 'benefitType']),
            'message' => 'Employee benefit updated successfully.',
        ]);
    }

    /**
     * Delete an employee benefit record.
     */
    public function destroy(EmployeeBenefit $employeeBenefit): JsonResponse
    {
        $employeeBenefit->delete();

        return response()->json(['message' => 'Employee benefit deleted successfully.']);
    }
}
