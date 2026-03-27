<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryComponent;
use App\Models\SalaryRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrEmployeeSalaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeSalary::query()
            ->with(['employee:id,employee_id,full_name,department_id', 'salaryComponent']);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $salaries = $query->orderByDesc('effective_from')->paginate($request->get('per_page', 20));

        return response()->json($salaries);
    }

    public function show(int $employeeId): JsonResponse
    {
        $employee = Employee::with([
            'activeSalaries.salaryComponent',
            'department',
        ])->findOrFail($employeeId);

        return response()->json(['data' => $employee]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'salary_component_id' => ['required', 'exists:salary_components,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        $salary = EmployeeSalary::create($validated);

        return response()->json([
            'data' => $salary->load(['employee:id,employee_id,full_name', 'salaryComponent']),
            'message' => 'Salary record created successfully.',
        ], 201);
    }

    public function update(Request $request, EmployeeSalary $employeeSalary): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'reason' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $employeeSalary, $validated) {
            $oldAmount = $employeeSalary->amount;
            $newAmount = $validated['amount'];

            // End current record
            $employeeSalary->update([
                'effective_to' => now()->subDay()->toDateString(),
            ]);

            // Create new salary record
            $newSalary = EmployeeSalary::create([
                'employee_id' => $employeeSalary->employee_id,
                'salary_component_id' => $employeeSalary->salary_component_id,
                'amount' => $newAmount,
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
            ]);

            // Create revision record
            SalaryRevision::create([
                'employee_id' => $employeeSalary->employee_id,
                'salary_component_id' => $employeeSalary->salary_component_id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'effective_date' => $validated['effective_from'],
                'reason' => $validated['reason'] ?? null,
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'data' => $newSalary->load(['employee:id,employee_id,full_name', 'salaryComponent']),
                'message' => 'Salary updated successfully.',
            ]);
        });
    }

    public function revisions(int $employeeId): JsonResponse
    {
        $revisions = SalaryRevision::where('employee_id', $employeeId)
            ->with(['salaryComponent', 'changedByUser:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $revisions]);
    }

    public function bulkRevision(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'exists:employees,id'],
            'salary_component_id' => ['required', 'exists:salary_components,id'],
            'adjustment_type' => ['required', 'in:percentage,fixed'],
            'adjustment_value' => ['required', 'numeric'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        $component = SalaryComponent::findOrFail($validated['salary_component_id']);
        $updatedCount = 0;

        DB::transaction(function () use ($request, $validated, &$updatedCount) {
            foreach ($validated['employee_ids'] as $employeeId) {
                $currentSalary = EmployeeSalary::forEmployee($employeeId)
                    ->where('salary_component_id', $validated['salary_component_id'])
                    ->active()
                    ->first();

                if (! $currentSalary) {
                    continue;
                }

                $oldAmount = (float) $currentSalary->amount;

                if ($validated['adjustment_type'] === 'percentage') {
                    $newAmount = $oldAmount * (1 + $validated['adjustment_value'] / 100);
                } else {
                    $newAmount = $oldAmount + $validated['adjustment_value'];
                }

                $newAmount = max(0, round($newAmount, 2));

                $currentSalary->update([
                    'effective_to' => now()->subDay()->toDateString(),
                ]);

                EmployeeSalary::create([
                    'employee_id' => $employeeId,
                    'salary_component_id' => $validated['salary_component_id'],
                    'amount' => $newAmount,
                    'effective_from' => $validated['effective_from'],
                ]);

                SalaryRevision::create([
                    'employee_id' => $employeeId,
                    'salary_component_id' => $validated['salary_component_id'],
                    'old_amount' => $oldAmount,
                    'new_amount' => $newAmount,
                    'effective_date' => $validated['effective_from'],
                    'reason' => $validated['reason'] ?? null,
                    'changed_by' => $request->user()->id,
                ]);

                $updatedCount++;
            }
        });

        return response()->json([
            'message' => "Bulk salary revision applied to {$updatedCount} employees.",
            'updated_count' => $updatedCount,
        ]);
    }
}
