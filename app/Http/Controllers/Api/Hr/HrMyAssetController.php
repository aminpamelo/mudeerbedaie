<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AssetAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyAssetController extends Controller
{
    /**
     * List the current employee's active asset assignments.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $assignments = AssetAssignment::query()
            ->with(['asset.category'])
            ->where('employee_id', $employee->id)
            ->active()
            ->orderByDesc('assigned_date')
            ->get();

        return response()->json(['data' => $assignments]);
    }
}
