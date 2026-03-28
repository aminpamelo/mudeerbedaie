<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyMeetingController extends Controller
{
    /**
     * Get meetings where the authenticated user's employee is an attendee.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $query = Meeting::query()
            ->whereHas('attendees', fn ($q) => $q->where('employee_id', $employee->id))
            ->with(['organizer:id,full_name', 'series:id,name'])
            ->withCount(['attendees', 'tasks', 'decisions']);

        if ($tab = $request->get('tab')) {
            if ($tab === 'upcoming') {
                $query->where('meeting_date', '>=', now()->toDateString());
            } elseif ($tab === 'past') {
                $query->where('meeting_date', '<', now()->toDateString());
            }
        }

        $query->orderBy('meeting_date', 'desc');

        $meetings = $query->paginate($request->get('per_page', 15));

        return response()->json($meetings);
    }
}
