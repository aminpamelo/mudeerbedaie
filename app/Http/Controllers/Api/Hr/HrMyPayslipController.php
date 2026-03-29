<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrPayslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HrMyPayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return response()->json(['message' => 'No employee profile found.'], 404);
        }

        $query = HrPayslip::where('employee_id', $employee->id);

        if ($year = $request->get('year')) {
            $query->where('year', $year);
        }

        $payslips = $query->orderByDesc('year')
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

        $payslipData = $payslip->toArray();
        $payslipData['items'] = $items;

        return response()->json([
            'data' => $payslipData,
        ]);
    }

    public function pdf(Request $request, HrPayslip $payslip): Response
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee || $payslip->employee_id !== $employee->id) {
            abort(404, 'Payslip not found.');
        }

        $payslip->load(['payrollRun', 'employee.department', 'employee.position']);

        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        $earnings = collect();
        $deductions = collect();

        if ($payslip->payroll_run_id && $payslip->payrollRun) {
            $items = $payslip->payrollRun->items()
                ->where('employee_id', $payslip->employee_id)
                ->get();

            $earnings = $items->where('type', 'earning');
            $deductions = $items->where('type', 'deduction')->filter(fn ($i) => ! ($i->is_statutory ?? false));
        }

        $companyName = config('app.name', 'Company');

        $pdf = Pdf::loadView('pdf.payslip', [
            'payslip' => $payslip,
            'employee' => $employee,
            'monthName' => $months[$payslip->month] ?? 'Unknown',
            'companyName' => $companyName,
            'earnings' => $earnings,
            'deductions' => $deductions,
        ]);

        $filename = "payslip-{$months[$payslip->month]}-{$payslip->year}.pdf";

        return $pdf->download($filename);
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
