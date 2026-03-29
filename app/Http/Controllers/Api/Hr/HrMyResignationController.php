<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ResignationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyResignationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $existing = ResignationRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You already have an active resignation request.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string'],
            'requested_last_date' => ['nullable', 'date'],
        ]);

        $noticePeriod = ResignationRequest::calculateNoticePeriod($employee);
        $submittedDate = now();

        $resignation = ResignationRequest::create([
            'employee_id' => $employee->id,
            'submitted_date' => $submittedDate,
            'reason' => $validated['reason'],
            'notice_period_days' => $noticePeriod,
            'last_working_date' => $submittedDate->copy()->addDays($noticePeriod),
            'requested_last_date' => $validated['requested_last_date'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Resignation submitted. Notice period: '.$noticePeriod.' days.',
            'data' => $resignation,
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $resignation = ResignationRequest::where('employee_id', $employee->id)
            ->with(['approver:id,full_name', 'exitChecklist.items'])
            ->latest()
            ->first();

        return response()->json(['data' => $resignation]);
    }
}
