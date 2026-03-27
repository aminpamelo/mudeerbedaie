<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\Hr\PayrollProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPayrollRunController extends Controller
{
    public function __construct(
        private PayrollProcessingService $payrollService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PayrollRun::query()
            ->with(['preparedBy:id,name', 'approvedBy:id,name']);

        if ($year = $request->get('year')) {
            $query->forYear($year);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $runs = $query->orderByDesc('year')->orderByDesc('month')
            ->paginate($request->get('per_page', 15));

        return response()->json($runs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020'],
            'notes' => ['nullable', 'string'],
        ]);

        $exists = PayrollRun::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Payroll run already exists for this month/year.',
            ], 422);
        }

        $run = PayrollRun::create(array_merge($validated, [
            'status' => 'draft',
            'prepared_by' => $request->user()->id,
        ]));

        return response()->json([
            'data' => $run->load('preparedBy:id,name'),
            'message' => 'Payroll run created successfully.',
        ], 201);
    }

    public function show(PayrollRun $payrollRun): JsonResponse
    {
        $payrollRun->load([
            'preparedBy:id,name',
            'reviewedBy:id,name',
            'approvedBy:id,name',
            'items' => function ($query) {
                $query->with('employee:id,employee_id,full_name,department_id')
                    ->orderBy('employee_id')
                    ->orderBy('type');
            },
        ]);

        return response()->json(['data' => $payrollRun]);
    }

    public function destroy(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft payroll runs can be deleted.',
            ], 422);
        }

        $payrollRun->delete();

        return response()->json(['message' => 'Payroll run deleted successfully.']);
    }

    public function calculate(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'draft') {
            return response()->json([
                'message' => 'Can only calculate draft payroll runs.',
            ], 422);
        }

        $this->payrollService->calculateAll($payrollRun);
        $payrollRun->refresh()->load('items.employee:id,employee_id,full_name');

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll calculated successfully.',
        ]);
    }

    public function calculateEmployee(PayrollRun $payrollRun, int $employeeId): JsonResponse
    {
        if (! in_array($payrollRun->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Cannot recalculate in current status.',
            ], 422);
        }

        $employee = Employee::with(['activeSalaries.salaryComponent', 'taxProfile'])
            ->findOrFail($employeeId);

        $this->payrollService->calculateForEmployee($payrollRun, $employee);

        return response()->json(['message' => 'Employee payroll recalculated.']);
    }

    public function submitReview(PayrollRun $payrollRun, Request $request): JsonResponse
    {
        if ($payrollRun->status !== 'draft') {
            return response()->json(['message' => 'Can only submit draft runs for review.'], 422);
        }

        if ($payrollRun->employee_count === 0) {
            return response()->json(['message' => 'Calculate payroll before submitting for review.'], 422);
        }

        $payrollRun->update([
            'status' => 'review',
            'reviewed_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll submitted for review.',
        ]);
    }

    public function approve(PayrollRun $payrollRun, Request $request): JsonResponse
    {
        if ($payrollRun->status !== 'review') {
            return response()->json(['message' => 'Can only approve runs in review status.'], 422);
        }

        $payrollRun->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll approved.',
        ]);
    }

    public function returnToDraft(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'review') {
            return response()->json(['message' => 'Can only return runs in review status.'], 422);
        }

        $payrollRun->update([
            'status' => 'draft',
            'reviewed_by' => null,
        ]);

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll returned to draft.',
        ]);
    }

    public function finalize(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'approved') {
            return response()->json(['message' => 'Can only finalize approved runs.'], 422);
        }

        DB::transaction(function () use ($payrollRun) {
            $this->payrollService->generatePayslips($payrollRun);

            $payrollRun->update([
                'status' => 'finalized',
                'finalized_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll finalized. Payslips generated.',
        ]);
    }
}
