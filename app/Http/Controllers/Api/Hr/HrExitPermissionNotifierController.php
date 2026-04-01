<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ExitPermissionNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrExitPermissionNotifierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExitPermissionNotifier::with(['department', 'employee'])
            ->orderBy('department_id');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        $notifier = ExitPermissionNotifier::firstOrCreate($validated);

        return response()->json([
            'data' => $notifier->load(['department', 'employee']),
            'message' => 'Notifier added.',
        ], 201);
    }

    public function destroy(ExitPermissionNotifier $exitPermissionNotifier): JsonResponse
    {
        $exitPermissionNotifier->delete();

        return response()->json(['message' => 'Notifier removed.']);
    }
}
