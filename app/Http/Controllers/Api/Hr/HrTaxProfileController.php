<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeTaxProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTaxProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['taxProfile', 'department:id,name'])
            ->select('id', 'employee_id', 'full_name', 'department_id', 'status');

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->get('status', 'active')) {
            $query->where('status', $status);
        }

        $employees = $query->orderBy('full_name')
            ->paginate($request->get('per_page', 20));

        return response()->json($employees);
    }

    public function show(int $employeeId): JsonResponse
    {
        $employee = Employee::with(['taxProfile', 'department:id,name'])
            ->select('id', 'employee_id', 'full_name', 'department_id', 'status')
            ->findOrFail($employeeId);

        return response()->json(['data' => $employee]);
    }

    public function update(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $validated = $request->validate([
            'tax_number' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['required', 'in:single,married_spouse_not_working,married_spouse_working'],
            'num_children' => ['required', 'integer', 'min:0', 'max:20'],
            'num_children_studying' => ['required', 'integer', 'min:0', 'lte:num_children'],
            'disabled_individual' => ['boolean'],
            'disabled_spouse' => ['boolean'],
            'is_pcb_manual' => ['boolean'],
            'manual_pcb_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $profile = EmployeeTaxProfile::updateOrCreate(
            ['employee_id' => $employeeId],
            $validated
        );

        return response()->json([
            'data' => $profile->load('employee:id,employee_id,full_name'),
            'message' => 'Tax profile updated successfully.',
        ]);
    }
}
