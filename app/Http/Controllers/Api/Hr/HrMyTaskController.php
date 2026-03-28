<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyTaskController extends Controller
{
    /**
     * Get tasks assigned to the authenticated user's employee.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $query = Task::query()
            ->where('assigned_to', $employee->id)
            ->with(['assigner:id,full_name', 'taskable']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $query->orderBy('deadline', 'asc');

        $tasks = $query->paginate($request->get('per_page', 15));

        return response()->json($tasks);
    }
}
