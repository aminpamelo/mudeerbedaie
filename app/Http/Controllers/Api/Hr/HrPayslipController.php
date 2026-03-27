<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrPayslip;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HrPayslip::query()
            ->with(['employee:id,employee_id,full_name,department_id', 'payrollRun']);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($month = $request->get('month')) {
            $query->where('month', $month);
        }

        if ($year = $request->get('year')) {
            $query->where('year', $year);
        }

        $payslips = $query->orderByDesc('year')->orderByDesc('month')
            ->paginate($request->get('per_page', 20));

        return response()->json($payslips);
    }

    public function show(HrPayslip $payslip): JsonResponse
    {
        $payslip->load([
            'employee.department',
            'employee.position',
            'payrollRun',
        ]);

        // Load payroll items for this employee in this run
        $items = [];
        if ($payslip->payroll_run_id) {
            $items = $payslip->payrollRun?->items()
                ->where('employee_id', $payslip->employee_id)
                ->orderBy('type')
                ->get();
        }

        return response()->json([
            'data' => $payslip,
            'items' => $items,
        ]);
    }

    public function pdf(HrPayslip $payslip): JsonResponse
    {
        // PDF generation will be implemented in Task 43 (PDF Payslip Template)
        return response()->json([
            'message' => 'PDF generation not yet implemented. Configure DomPDF in Task 43.',
            'payslip_id' => $payslip->id,
        ], 501);
    }

    public function bulkPdf(PayrollRun $payrollRun): JsonResponse
    {
        // Bulk PDF/ZIP generation will be implemented in Task 43
        return response()->json([
            'message' => 'Bulk PDF generation not yet implemented. Configure DomPDF in Task 43.',
            'payroll_run_id' => $payrollRun->id,
        ], 501);
    }
}
