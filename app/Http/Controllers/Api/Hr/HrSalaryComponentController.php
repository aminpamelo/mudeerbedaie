<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrSalaryComponentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SalaryComponent::query();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $components = $query->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => $components]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:salary_components,code'],
            'type' => ['required', 'in:earning,deduction'],
            'category' => ['required', 'in:basic,fixed_allowance,variable_allowance,fixed_deduction,variable_deduction'],
            'is_taxable' => ['boolean'],
            'is_epf_applicable' => ['boolean'],
            'is_socso_applicable' => ['boolean'],
            'is_eis_applicable' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $component = SalaryComponent::create($validated);

        return response()->json([
            'data' => $component,
            'message' => 'Salary component created successfully.',
        ], 201);
    }

    public function update(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:salary_components,code,'.$salaryComponent->id],
            'type' => ['required', 'in:earning,deduction'],
            'category' => ['required', 'in:basic,fixed_allowance,variable_allowance,fixed_deduction,variable_deduction'],
            'is_taxable' => ['boolean'],
            'is_epf_applicable' => ['boolean'],
            'is_socso_applicable' => ['boolean'],
            'is_eis_applicable' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $salaryComponent->update($validated);

        return response()->json([
            'data' => $salaryComponent->fresh(),
            'message' => 'Salary component updated successfully.',
        ]);
    }

    public function destroy(SalaryComponent $salaryComponent): JsonResponse
    {
        if ($salaryComponent->is_system) {
            return response()->json([
                'message' => 'System salary components cannot be deleted.',
            ], 422);
        }

        $salaryComponent->delete();

        return response()->json(['message' => 'Salary component deleted successfully.']);
    }
}
