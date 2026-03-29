<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrPayslip;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayrollReportController extends Controller
{
    public function monthlySummary(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $run = PayrollRun::where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $run) {
            return response()->json(['data' => [], 'message' => 'No payroll run found for this period.']);
        }

        $summary = PayrollItem::where('payroll_run_id', $run->id)
            ->with(['employee:id,employee_id,full_name,department_id', 'employee.department:id,name'])
            ->get()
            ->groupBy('employee_id')
            ->map(function ($items) {
                $employee = $items->first()->employee;
                $earnings = $items->where('type', 'earning')->sum('amount');
                $deductions = $items->where('type', 'deduction')->sum('amount');

                $byCode = $items->groupBy(fn ($item) => strtoupper($item->component_code ?? ''));

                return [
                    'employee_name' => $employee?->full_name ?? 'Unknown',
                    'department' => $employee?->department?->name ?? '-',
                    'gross_pay' => $earnings,
                    'epf_employee' => $byCode->get('EPF_EE', collect())->sum('amount'),
                    'socso_employee' => $byCode->get('SOCSO_EE', collect())->sum('amount'),
                    'eis_employee' => $byCode->get('EIS_EE', collect())->sum('amount'),
                    'pcb' => $byCode->get('PCB', collect())->sum('amount'),
                    'net_pay' => $earnings - $deductions,
                ];
            })
            ->sortBy('employee_name')
            ->values();

        return response()->json(['data' => $summary, 'month' => $month, 'year' => $year]);
    }

    public function statutory(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $run = PayrollRun::where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $run) {
            return response()->json(['data' => [], 'month' => $month, 'year' => $year]);
        }

        $summary = PayrollItem::where('payroll_run_id', $run->id)
            ->with(['employee:id,employee_id,full_name,department_id', 'employee.department:id,name'])
            ->get()
            ->groupBy('employee_id')
            ->map(function ($items) {
                $employee = $items->first()->employee;
                $byCode = $items->groupBy(fn ($item) => strtoupper($item->component_code ?? ''));

                return [
                    'employee_name' => $employee?->full_name ?? 'Unknown',
                    'epf_employee' => $byCode->get('EPF_EE', collect())->sum('amount'),
                    'epf_employer' => $byCode->get('EPF_ER', collect())->sum('amount'),
                    'socso_employee' => $byCode->get('SOCSO_EE', collect())->sum('amount'),
                    'socso_employer' => $byCode->get('SOCSO_ER', collect())->sum('amount'),
                    'eis_employee' => $byCode->get('EIS_EE', collect())->sum('amount'),
                    'eis_employer' => $byCode->get('EIS_ER', collect())->sum('amount'),
                    'pcb' => $byCode->get('PCB', collect())->sum('amount'),
                ];
            })
            ->sortBy('employee_name')
            ->values();

        return response()->json(['data' => $summary, 'month' => $month, 'year' => $year]);
    }

    public function bankPayment(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $payslips = HrPayslip::where('month', $month)
            ->where('year', $year)
            ->with(['employee' => function ($query) {
                $query->select('id', 'employee_id', 'full_name', 'bank_name', 'bank_account_number');
            }])
            ->orderBy('employee_id')
            ->get()
            ->map(fn ($payslip) => [
                'employee_id' => $payslip->employee?->employee_id,
                'employee_name' => $payslip->employee?->full_name,
                'bank_name' => $payslip->employee?->bank_name,
                'account_number' => $payslip->employee?->bank_account_number,
                'net_pay' => $payslip->net_salary,
            ]);

        return response()->json(['data' => $payslips, 'month' => $month, 'year' => $year]);
    }

    public function ytd(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $employeeId = $request->get('employee_id');

        $query = HrPayslip::where('year', $year)
            ->with(['employee:id,employee_id,full_name']);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $ytd = $query->get()
            ->groupBy('employee_id')
            ->map(function ($payslips) {
                $employee = $payslips->first()->employee;

                return [
                    'employee_id' => $employee?->employee_id,
                    'employee_name' => $employee?->full_name,
                    'ytd_gross' => $payslips->sum('gross_salary'),
                    'ytd_epf_employee' => $payslips->sum('epf_employee'),
                    'ytd_epf_employer' => $payslips->sum('epf_employer'),
                    'ytd_socso_employee' => $payslips->sum('socso_employee'),
                    'ytd_socso_employer' => $payslips->sum('socso_employer'),
                    'ytd_eis_employee' => $payslips->sum('eis_employee'),
                    'ytd_eis_employer' => $payslips->sum('eis_employer'),
                    'ytd_pcb' => $payslips->sum('pcb_amount'),
                    'ytd_net' => $payslips->sum('net_salary'),
                    'months_paid' => $payslips->count(),
                ];
            })
            ->values();

        return response()->json(['data' => $ytd, 'year' => $year]);
    }

    public function eaForm(int $employeeId): JsonResponse
    {
        // EA Form PDF generation will be implemented in Task 43
        return response()->json([
            'message' => 'EA Form PDF generation not yet implemented.',
            'employee_id' => $employeeId,
        ], 501);
    }

    public function eaForms(int $year): JsonResponse
    {
        // Bulk EA Forms ZIP generation will be implemented in Task 43
        return response()->json([
            'message' => 'Bulk EA Forms generation not yet implemented.',
            'year' => $year,
        ], 501);
    }
}
