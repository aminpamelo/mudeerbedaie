<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrPayslip;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayrollDashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $run = PayrollRun::where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $run) {
            // Find the latest finalized run
            $run = PayrollRun::where('status', 'finalized')
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();
        }

        if (! $run) {
            return response()->json([
                'data' => [
                    'total_gross' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                    'total_employer_cost' => 0,
                    'employee_count' => 0,
                    'status' => null,
                    'month' => $month,
                    'year' => $year,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'total_gross' => $run->total_gross,
                'total_deductions' => $run->total_deductions,
                'total_net' => $run->total_net,
                'total_employer_cost' => $run->total_employer_cost,
                'employee_count' => $run->employee_count,
                'status' => $run->status,
                'month' => $run->month,
                'year' => $run->year,
            ],
        ]);
    }

    public function trend(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $runs = PayrollRun::where('year', $year)
            ->where('status', 'finalized')
            ->orderBy('month')
            ->get()
            ->map(fn ($run) => [
                'month' => $run->month,
                'month_name' => $run->month_name,
                'total_gross' => $run->total_gross,
                'total_net' => $run->total_net,
                'total_deductions' => $run->total_deductions,
                'total_employer_cost' => $run->total_employer_cost,
                'employee_count' => $run->employee_count,
            ]);

        return response()->json(['data' => $runs, 'year' => $year]);
    }

    public function statutoryBreakdown(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $payslips = HrPayslip::where('month', $month)
            ->where('year', $year)
            ->get();

        $breakdown = [
            ['label' => 'EPF (Employee)', 'value' => $payslips->sum('epf_employee')],
            ['label' => 'EPF (Employer)', 'value' => $payslips->sum('epf_employer')],
            ['label' => 'SOCSO (Employee)', 'value' => $payslips->sum('socso_employee')],
            ['label' => 'SOCSO (Employer)', 'value' => $payslips->sum('socso_employer')],
            ['label' => 'EIS (Employee)', 'value' => $payslips->sum('eis_employee')],
            ['label' => 'EIS (Employer)', 'value' => $payslips->sum('eis_employer')],
            ['label' => 'PCB / MTD', 'value' => $payslips->sum('pcb_amount')],
        ];

        return response()->json([
            'data' => $breakdown,
            'month' => $month,
            'year' => $year,
        ]);
    }
}
