<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrPayslip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyPayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return response()->json(['message' => 'No employee profile found.'], 404);
        }

        $payslips = HrPayslip::where('employee_id', $employee->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->get('per_page', 12));

        return response()->json($payslips);
    }

    public function show(Request $request, HrPayslip $payslip): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee || $payslip->employee_id !== $employee->id) {
            return response()->json(['message' => 'Payslip not found.'], 404);
        }

        $payslip->load(['payrollRun', 'employee.department']);

        // Load payroll items for this employee
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

    public function pdf(Request $request, HrPayslip $payslip): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee || $payslip->employee_id !== $employee->id) {
            return response()->json(['message' => 'Payslip not found.'], 404);
        }

        // PDF generation will be implemented in Task 43
        return response()->json([
            'message' => 'PDF generation not yet implemented.',
            'payslip_id' => $payslip->id,
        ], 501);
    }

    public function ytd(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return response()->json(['message' => 'No employee profile found.'], 404);
        }

        $year = $request->get('year', now()->year);

        $payslips = HrPayslip::where('employee_id', $employee->id)
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $ytd = [
            'year' => $year,
            'ytd_gross' => $payslips->sum('gross_salary'),
            'ytd_deductions' => $payslips->sum('total_deductions'),
            'ytd_net' => $payslips->sum('net_salary'),
            'ytd_epf_employee' => $payslips->sum('epf_employee'),
            'ytd_socso_employee' => $payslips->sum('socso_employee'),
            'ytd_eis_employee' => $payslips->sum('eis_employee'),
            'ytd_pcb' => $payslips->sum('pcb_amount'),
            'months_paid' => $payslips->count(),
            'monthly_breakdown' => $payslips->map(fn ($p) => [
                'month' => $p->month,
                'gross' => $p->gross_salary,
                'net' => $p->net_salary,
            ]),
        ];

        return response()->json(['data' => $ytd]);
    }
}
