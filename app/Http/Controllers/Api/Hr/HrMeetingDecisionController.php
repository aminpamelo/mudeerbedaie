<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDecisionRequest;
use App\Models\Meeting;
use App\Models\MeetingDecision;
use Illuminate\Http\JsonResponse;

class HrMeetingDecisionController extends Controller
{
    /**
     * Create a new decision for a meeting.
     */
    public function store(StoreDecisionRequest $request, Meeting $meeting): JsonResponse
    {
        $decision = $meeting->decisions()->create([
            ...$request->validated(),
            'decided_at' => now(),
        ]);

        $decision->load('decidedBy:id,full_name');

        return response()->json([
            'data' => $decision,
            'message' => 'Decision recorded successfully.',
        ], 201);
    }

    /**
     * Update a decision.
     */
    public function update(StoreDecisionRequest $request, Meeting $meeting, MeetingDecision $decision): JsonResponse
    {
        $decision->update($request->validated());

        $decision->load('decidedBy:id,full_name');

        return response()->json([
            'data' => $decision,
            'message' => 'Decision updated successfully.',
        ]);
    }

    /**
     * Delete a decision.
     */
    public function destroy(Meeting $meeting, MeetingDecision $decision): JsonResponse
    {
        $decision->delete();

        return response()->json(['message' => 'Decision deleted successfully.']);
    }
}
