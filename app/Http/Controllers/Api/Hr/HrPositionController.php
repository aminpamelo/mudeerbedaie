<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePositionRequest;
use App\Http\Requests\Hr\UpdatePositionRequest;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPositionController extends Controller
{
    /**
     * List positions with department and employee count.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::query()
            ->with('department:id,name')
            ->withCount('employees');

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($search = $request->get('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $positions = $query->orderBy('title')->get();

        return response()->json(['data' => $positions]);
    }

    /**
     * Create a position.
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());
        $position->load('department:id,name');

        return response()->json([
            'data' => $position,
            'message' => 'Position created successfully.',
        ], 201);
    }

    /**
     * Show a position with department.
     */
    public function show(Position $position): JsonResponse
    {
        $position->load('department:id,name')->loadCount('employees');

        return response()->json(['data' => $position]);
    }

    /**
     * Update a position.
     */
    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $position->update($request->validated());
        $position->load('department:id,name');

        return response()->json([
            'data' => $position,
            'message' => 'Position updated successfully.',
        ]);
    }

    /**
     * Delete a position (only if no employees assigned).
     */
    public function destroy(Position $position): JsonResponse
    {
        if ($position->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete position with assigned employees.',
            ], 422);
        }

        $position->delete();

        return response()->json(['message' => 'Position deleted successfully.']);
    }
}
