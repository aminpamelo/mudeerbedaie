<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreMeetingRequest;
use App\Http\Requests\Hr\UpdateMeetingRequest;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Notifications\Hr\MeetingInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrMeetingController extends Controller
{
    /**
     * Paginated list with search, filters, and counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Meeting::query()
            ->with(['organizer:id,full_name', 'noteTaker:id,full_name', 'series:id,name'])
            ->withCount(['attendees', 'tasks', 'decisions']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($seriesId = $request->get('meeting_series_id')) {
            $query->where('meeting_series_id', $seriesId);
        }

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

    /**
     * Create a new meeting with attendees and agenda items.
     */
    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {
            $employee = $request->user()->employee;

            $meeting = Meeting::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'location' => $validated['location'] ?? null,
                'meeting_date' => $validated['meeting_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'meeting_series_id' => $validated['meeting_series_id'] ?? null,
                'note_taker_id' => $validated['note_taker_id'] ?? null,
                'organizer_id' => $employee?->id,
                'created_by' => $request->user()->id,
            ]);

            // Add organizer as attendee
            if ($employee) {
                MeetingAttendee::create([
                    'meeting_id' => $meeting->id,
                    'employee_id' => $employee->id,
                    'role' => 'organizer',
                    'attendance_status' => 'invited',
                ]);
            }

            // Add note taker as attendee if provided and different from organizer
            if (! empty($validated['note_taker_id']) && $validated['note_taker_id'] !== $employee?->id) {
                MeetingAttendee::create([
                    'meeting_id' => $meeting->id,
                    'employee_id' => $validated['note_taker_id'],
                    'role' => 'note_taker',
                    'attendance_status' => 'invited',
                ]);
            }

            // Add additional attendees
            if (! empty($validated['attendee_ids'])) {
                $existingIds = [$employee?->id, $validated['note_taker_id'] ?? null];
                $existingIds = array_filter($existingIds);

                foreach ($validated['attendee_ids'] as $attendeeId) {
                    if (! in_array($attendeeId, $existingIds)) {
                        MeetingAttendee::create([
                            'meeting_id' => $meeting->id,
                            'employee_id' => $attendeeId,
                            'role' => 'attendee',
                            'attendance_status' => 'invited',
                        ]);
                    }
                }
            }

            // Create agenda items
            if (! empty($validated['agenda_items'])) {
                foreach ($validated['agenda_items'] as $index => $item) {
                    $meeting->agendaItems()->create([
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            // Send MeetingInvitationNotification to all attendees
            $meeting->load(['attendees.employee.user']);
            foreach ($meeting->attendees as $attendee) {
                if ($attendee->employee?->user) {
                    $attendee->employee->user->notify(new MeetingInvitationNotification($meeting));
                }
            }

            $meeting->load([
                'organizer:id,full_name',
                'noteTaker:id,full_name',
                'attendees.employee:id,full_name',
                'agendaItems',
            ]);

            return response()->json([
                'data' => $meeting,
                'message' => 'Meeting created successfully.',
            ], 201);
        });
    }

    /**
     * Show meeting with all relationships.
     */
    public function show(Meeting $meeting): JsonResponse
    {
        $meeting->load([
            'organizer:id,full_name',
            'noteTaker:id,full_name',
            'series:id,name',
            'attendees.employee:id,full_name',
            'agendaItems',
            'decisions.decidedBy:id,full_name',
            'tasks.assignee:id,full_name',
            'tasks.subtasks',
            'attachments.uploader:id,full_name',
            'recordings.uploader:id,full_name',
            'recordings.transcripts',
            'aiSummaries',
        ]);

        return response()->json(['data' => $meeting]);
    }

    /**
     * Update meeting fields.
     */
    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $meeting->update($request->validated());

        $meeting->load([
            'organizer:id,full_name',
            'noteTaker:id,full_name',
            'series:id,name',
        ]);

        return response()->json([
            'data' => $meeting,
            'message' => 'Meeting updated successfully.',
        ]);
    }

    /**
     * Soft delete a meeting.
     */
    public function destroy(Meeting $meeting): JsonResponse
    {
        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted successfully.']);
    }

    /**
     * Update the status of a meeting.
     */
    public function updateStatus(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,scheduled,in_progress,completed,cancelled'],
        ]);

        $meeting->update(['status' => $validated['status']]);

        return response()->json([
            'data' => $meeting,
            'message' => 'Meeting status updated successfully.',
        ]);
    }
}
