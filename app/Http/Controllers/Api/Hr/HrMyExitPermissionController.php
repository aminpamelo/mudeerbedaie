<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreExitPermissionRequest;
use App\Models\OfficeExitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyExitPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['data' => []], 200);
        }

        $query = OfficeExitPermission::where('employee_id', $employee->id)
            ->with('approver')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['data' => $query->paginate(15)]);
    }

    public function store(StoreExitPermissionRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 422);
        }

        $permission = OfficeExitPermission::create([
            ...$request->validated(),
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => $permission->load('employee'),
            'message' => 'Exit permission request submitted successfully.',
        ], 201);
    }

    public function show(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        $employee = $request->user()->employee;

        if ($officeExitPermission->employee_id !== $employee?->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'data' => $officeExitPermission->load(['employee.department', 'approver']),
        ]);
    }

    public function cancel(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        $employee = $request->user()->employee;

        if ($officeExitPermission->employee_id !== $employee?->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $officeExitPermission->isPending()) {
            return response()->json(['message' => 'Only pending requests can be cancelled.'], 422);
        }

        $officeExitPermission->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Exit permission request cancelled.']);
    }
}
