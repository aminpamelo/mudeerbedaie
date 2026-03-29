<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExitChecklist;
use App\Models\ResignationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrResignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ResignationRequest::query()
            ->with(['employee:id,full_name,employee_id,department_id', 'employee.department:id,name']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $resignations = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($resignations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'submitted_date' => ['required', 'date'],
            'reason' => ['required', 'string'],
            'requested_last_date' => ['nullable', 'date'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $noticePeriod = ResignationRequest::calculateNoticePeriod($employee);
        $submittedDate = \Carbon\Carbon::parse($validated['submitted_date']);

        $resignation = ResignationRequest::create(array_merge($validated, [
            'notice_period_days' => $noticePeriod,
            'last_working_date' => $submittedDate->copy()->addDays($noticePeriod),
            'status' => 'pending',
        ]));

        return response()->json([
            'message' => 'Resignation request submitted.',
            'data' => $resignation->load('employee:id,full_name,employee_id'),
        ], 201);
    }

    public function show(ResignationRequest $resignationRequest): JsonResponse
    {
        return response()->json([
            'data' => $resignationRequest->load([
                'employee:id,full_name,employee_id,department_id,position_id,employment_type,join_date',
                'employee.department:id,name',
                'employee.position:id,title',
                'approver:id,full_name',
                'exitChecklist.items',
                'finalSettlement',
            ]),
        ]);
    }

    public function approve(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        $validated = $request->validate([
            'final_last_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $approver = Employee::where('user_id', $request->user()->id)->first();

        return DB::transaction(function () use ($validated, $resignationRequest, $approver) {
            $resignationRequest->update([
                'status' => 'approved',
                'approved_by' => $approver?->id,
                'approved_at' => now(),
                'final_last_date' => $validated['final_last_date'] ?? $resignationRequest->last_working_date,
                'notes' => $validated['notes'] ?? $resignationRequest->notes,
            ]);

            $checklist = ExitChecklist::create([
                'employee_id' => $resignationRequest->employee_id,
                'resignation_request_id' => $resignationRequest->id,
                'status' => 'in_progress',
            ]);

            $checklist->createDefaultItems();
            $checklist->addAssetReturnItems();

            return response()->json([
                'message' => 'Resignation approved. Exit checklist created.',
                'data' => $resignationRequest->fresh(['exitChecklist.items']),
            ]);
        });
    }

    public function reject(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $resignationRequest->update([
            'status' => 'rejected',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Resignation rejected.',
            'data' => $resignationRequest,
        ]);
    }

    public function complete(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        return DB::transaction(function () use ($resignationRequest) {
            $resignationRequest->update(['status' => 'completed']);

            $resignationRequest->employee->update([
                'status' => 'resigned',
                'resignation_date' => $resignationRequest->submitted_date,
                'last_working_date' => $resignationRequest->final_last_date ?? $resignationRequest->last_working_date,
            ]);

            return response()->json([
                'message' => 'Offboarding completed. Employee status updated to resigned.',
                'data' => $resignationRequest,
            ]);
        });
    }
}
