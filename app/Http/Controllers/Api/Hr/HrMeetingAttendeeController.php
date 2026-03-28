<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMeetingAttendeeController extends Controller
{
    /**
     * Add attendees to a meeting.
     */
    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['exists:employees,id'],
        ]);

        $existingIds = $meeting->attendees()->pluck('employee_id')->toArray();
        $added = [];

        foreach ($validated['employee_ids'] as $employeeId) {
            if (! in_array($employeeId, $existingIds)) {
                $added[] = MeetingAttendee::create([
                    'meeting_id' => $meeting->id,
                    'employee_id' => $employeeId,
                    'role' => 'attendee',
                    'attendance_status' => 'pending',
                ]);
            }
        }

        return response()->json([
            'data' => $added,
            'message' => count($added).' attendee(s) added successfully.',
        ], 201);
    }

    /**
     * Update attendance status for an attendee.
     */
    public function update(Request $request, Meeting $meeting, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'attendance_status' => ['required', 'in:pending,confirmed,declined,attended,absent'],
        ]);

        $attendee = MeetingAttendee::where('meeting_id', $meeting->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $attendee->update(['attendance_status' => $validated['attendance_status']]);

        return response()->json([
            'data' => $attendee,
            'message' => 'Attendance status updated successfully.',
        ]);
    }

    /**
     * Remove an attendee from a meeting.
     */
    public function destroy(Meeting $meeting, Employee $employee): JsonResponse
    {
        $deleted = MeetingAttendee::where('meeting_id', $meeting->id)
            ->where('employee_id', $employee->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Attendee not found.'], 404);
        }

        return response()->json(['message' => 'Attendee removed successfully.']);
    }
}
