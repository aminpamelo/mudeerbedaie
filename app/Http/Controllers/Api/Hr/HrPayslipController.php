<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrPayslip;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ZipArchive;

class HrPayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HrPayslip::query()
            ->with(['employee:id,employee_id,full_name,department_id', 'payrollRun']);

        if ($payrollRunId = $request->get('payroll_run_id')) {
            $query->where('payroll_run_id', $payrollRunId);
        }

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

    public function pdf(HrPayslip $payslip): Response
    {
        $payslip->load(['employee.department', 'employee.position']);

        $items = PayrollItem::where('payroll_run_id', $payslip->payroll_run_id)
            ->where('employee_id', $payslip->employee_id)
            ->orderBy('type')
            ->get();

        $settings = [
            'company_name' => PayrollSetting::getValue('company_name', config('app.name')),
            'company_address' => PayrollSetting::getValue('company_address', ''),
            'company_epf_number' => PayrollSetting::getValue('company_epf_number', ''),
            'company_socso_number' => PayrollSetting::getValue('company_socso_number', ''),
        ];

        $pdf = Pdf::loadView('hr.payslip-pdf', compact('payslip', 'items', 'settings'));
        $pdf->setPaper('a4');

        $filename = sprintf(
            'Payslip_%s_%04d_%02d.pdf',
            $payslip->employee->employee_id ?? $payslip->employee_id,
            $payslip->year,
            $payslip->month
        );

        return $pdf->download($filename);
    }

    public function bulkPdf(PayrollRun $payrollRun): Response|JsonResponse
    {
        $payslips = HrPayslip::where('payroll_run_id', $payrollRun->id)
            ->with(['employee.department', 'employee.position'])
            ->get();

        if ($payslips->isEmpty()) {
            return response()->json(['message' => 'No payslips found for this payroll run.'], 404);
        }

        $settings = [
            'company_name' => PayrollSetting::getValue('company_name', config('app.name')),
            'company_address' => PayrollSetting::getValue('company_address', ''),
            'company_epf_number' => PayrollSetting::getValue('company_epf_number', ''),
            'company_socso_number' => PayrollSetting::getValue('company_socso_number', ''),
        ];

        $tempDir = storage_path('app/temp/payslips_'.$payrollRun->id);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        foreach ($payslips as $payslip) {
            $items = PayrollItem::where('payroll_run_id', $payrollRun->id)
                ->where('employee_id', $payslip->employee_id)
                ->orderBy('type')
                ->get();

            $pdf = Pdf::loadView('hr.payslip-pdf', compact('payslip', 'items', 'settings'));
            $pdf->setPaper('a4');

            $filename = sprintf(
                'Payslip_%s_%04d_%02d.pdf',
                $payslip->employee->employee_id ?? $payslip->employee_id,
                $payslip->year,
                $payslip->month
            );

            $pdf->save($tempDir.'/'.$filename);
        }

        $zipPath = storage_path(sprintf('app/temp/Payslips_%04d_%02d.zip', $payrollRun->year, $payrollRun->month));
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (glob($tempDir.'/*.pdf') as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        // Cleanup temp PDFs
        array_map('unlink', glob($tempDir.'/*.pdf'));
        rmdir($tempDir);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
