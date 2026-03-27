<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrClaimReportController extends Controller
{
    /**
     * Generate claim reports with filters.
     */
    public function index(Request $request): JsonResponse|StreamedResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = ClaimRequest::query()
            ->with(['employee.department', 'claimType']);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($claimTypeId = $request->get('claim_type_id')) {
            $query->where('claim_type_id', $claimTypeId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('claim_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('claim_date', '<=', $dateTo);
        }

        if ($year = $request->get('year')) {
            $query->whereYear('claim_date', $year);
        }

        if ($month = $request->get('month')) {
            $query->whereMonth('claim_date', $month);
        }

        if ($request->get('export') === 'csv') {
            return $this->exportCsv($query->orderByDesc('claim_date')->get());
        }

        $byEmployee = ClaimRequest::query()
            ->select('employee_id', DB::raw('count(*) as count'), DB::raw('sum(approved_amount) as total'))
            ->with('employee')
            ->whereIn('status', ['approved', 'paid'])
            ->when($dateFrom, fn ($q) => $q->whereDate('claim_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('claim_date', '<=', $dateTo))
            ->groupBy('employee_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $byType = ClaimRequest::query()
            ->select('claim_type_id', DB::raw('count(*) as count'), DB::raw('sum(approved_amount) as total'))
            ->with('claimType')
            ->whereIn('status', ['approved', 'paid'])
            ->when($dateFrom, fn ($q) => $q->whereDate('claim_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('claim_date', '<=', $dateTo))
            ->groupBy('claim_type_id')
            ->orderByDesc('total')
            ->get();

        $requests = $query->orderByDesc('claim_date')->paginate(15);

        return response()->json([
            'data' => $requests,
            'summary' => [
                'by_employee' => $byEmployee,
                'by_type' => $byType,
            ],
        ]);
    }

    /**
     * Export claims as CSV.
     */
    private function exportCsv($claims): StreamedResponse
    {
        return response()->streamDownload(function () use ($claims) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Claim Number', 'Employee ID', 'Employee Name', 'Department',
                'Claim Type', 'Claim Date', 'Amount (RM)', 'Approved Amount (RM)',
                'Status', 'Description',
            ]);

            foreach ($claims as $claim) {
                fputcsv($handle, [
                    $claim->claim_number,
                    $claim->employee?->employee_id,
                    $claim->employee?->full_name,
                    $claim->employee?->department?->name,
                    $claim->claimType?->name,
                    $claim->claim_date,
                    $claim->amount,
                    $claim->approved_amount,
                    $claim->status,
                    $claim->description,
                ]);
            }

            fclose($handle);
        }, 'claims-report-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
