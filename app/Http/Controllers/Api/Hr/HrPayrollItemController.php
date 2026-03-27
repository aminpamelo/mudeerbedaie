<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayrollItemController extends Controller
{
    public function store(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        if (! in_array($payrollRun->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Can only add items to draft or review payroll runs.',
            ], 422);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'component_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:earning,deduction'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $item = PayrollItem::create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $validated['employee_id'],
            'component_name' => $validated['component_name'],
            'component_code' => 'ADHOC_'.strtoupper(str_replace(' ', '_', $validated['component_name'])),
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'is_statutory' => false,
        ]);

        return response()->json([
            'data' => $item,
            'message' => 'Payroll item added successfully.',
        ], 201);
    }

    public function update(Request $request, PayrollRun $payrollRun, PayrollItem $payrollItem): JsonResponse
    {
        if (! in_array($payrollRun->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Can only modify items in draft or review payroll runs.',
            ], 422);
        }

        if ($payrollItem->is_statutory) {
            return response()->json([
                'message' => 'Statutory items cannot be modified directly.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'component_name' => ['nullable', 'string', 'max:255'],
        ]);

        $payrollItem->update($validated);

        return response()->json([
            'data' => $payrollItem->fresh(),
            'message' => 'Payroll item updated successfully.',
        ]);
    }

    public function destroy(PayrollRun $payrollRun, PayrollItem $payrollItem): JsonResponse
    {
        if (! in_array($payrollRun->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Can only remove items from draft or review payroll runs.',
            ], 422);
        }

        if ($payrollItem->is_statutory) {
            return response()->json([
                'message' => 'Statutory items cannot be removed directly.',
            ], 422);
        }

        $payrollItem->delete();

        return response()->json(['message' => 'Payroll item removed successfully.']);
    }
}
