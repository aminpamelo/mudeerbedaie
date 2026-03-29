<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\FinalSettlement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HrFinalSettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settlements = FinalSettlement::query()
            ->with(['employee:id,full_name,employee_id,department_id', 'employee.department:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json($settlements);
    }

    public function calculate(Request $request, int $employeeId): JsonResponse
    {
        $validated = $request->validate([
            'final_last_date' => ['required', 'date'],
            'resignation_request_id' => ['nullable', 'exists:resignation_requests,id'],
            'other_earnings' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
        ]);

        $settlement = FinalSettlement::calculate($employeeId, $validated['final_last_date']);
        $settlement->resignation_request_id = $validated['resignation_request_id'] ?? null;
        $settlement->other_earnings = $validated['other_earnings'] ?? 0;
        $settlement->other_deductions = $validated['other_deductions'] ?? 0;

        $settlement->total_gross = $settlement->prorated_salary + $settlement->leave_encashment + $settlement->other_earnings;
        $settlement->total_deductions = $settlement->epf_employee + $settlement->socso_employee + $settlement->eis_employee + $settlement->pcb_amount + $settlement->other_deductions;
        $settlement->net_amount = $settlement->total_gross - $settlement->total_deductions;

        $settlement->save();

        return response()->json([
            'message' => 'Final settlement calculated.',
            'data' => $settlement->load('employee:id,full_name,employee_id'),
        ], 201);
    }

    public function show(FinalSettlement $finalSettlement): JsonResponse
    {
        return response()->json([
            'data' => $finalSettlement->load([
                'employee:id,full_name,employee_id,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,title',
                'resignationRequest',
                'approver:id,name',
            ]),
        ]);
    }

    public function update(Request $request, FinalSettlement $finalSettlement): JsonResponse
    {
        $validated = $request->validate([
            'other_earnings' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $finalSettlement->update($validated);

        $finalSettlement->total_gross = $finalSettlement->prorated_salary + $finalSettlement->leave_encashment + $finalSettlement->other_earnings;
        $finalSettlement->total_deductions = $finalSettlement->epf_employee + $finalSettlement->socso_employee + $finalSettlement->eis_employee + $finalSettlement->pcb_amount + $finalSettlement->other_deductions;
        $finalSettlement->net_amount = $finalSettlement->total_gross - $finalSettlement->total_deductions;
        $finalSettlement->save();

        return response()->json([
            'message' => 'Settlement updated.',
            'data' => $finalSettlement,
        ]);
    }

    public function approve(Request $request, FinalSettlement $finalSettlement): JsonResponse
    {
        $finalSettlement->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Settlement approved.',
            'data' => $finalSettlement,
        ]);
    }

    public function markPaid(FinalSettlement $finalSettlement): JsonResponse
    {
        $finalSettlement->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Settlement marked as paid.',
            'data' => $finalSettlement,
        ]);
    }

    public function pdf(FinalSettlement $finalSettlement): \Illuminate\Http\Response
    {
        $finalSettlement->load(['employee.department', 'employee.position']);

        $html = view('hr.pdf.final-settlement', [
            'settlement' => $finalSettlement,
            'employee' => $finalSettlement->employee,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $filename = "final_settlement_{$finalSettlement->employee->employee_id}.pdf";
        $path = "hr/settlements/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        $finalSettlement->update(['pdf_path' => $path]);

        return $pdf->download($filename);
    }
}
