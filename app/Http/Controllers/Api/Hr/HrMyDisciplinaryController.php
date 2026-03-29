<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyDisciplinaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $actions = DisciplinaryAction::where('employee_id', $employee->id)
            ->with('issuer:id,full_name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $actions]);
    }

    public function respond(Request $request, DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($disciplinaryAction->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($disciplinaryAction->status !== 'pending_response') {
            return response()->json(['message' => 'This action does not require a response.'], 422);
        }

        $validated = $request->validate([
            'employee_response' => ['required', 'string'],
        ]);

        $disciplinaryAction->update([
            'employee_response' => $validated['employee_response'],
            'responded_at' => now(),
            'status' => 'responded',
        ]);

        return response()->json([
            'message' => 'Response submitted.',
            'data' => $disciplinaryAction,
        ]);
    }
}
