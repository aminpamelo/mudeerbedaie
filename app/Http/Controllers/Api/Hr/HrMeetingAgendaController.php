<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreAgendaItemRequest;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMeetingAgendaController extends Controller
{
    /**
     * Create a new agenda item for a meeting.
     */
    public function store(StoreAgendaItemRequest $request, Meeting $meeting): JsonResponse
    {
        $maxOrder = $meeting->agendaItems()->max('sort_order') ?? 0;

        $agendaItem = $meeting->agendaItems()->create([
            ...$request->validated(),
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'data' => $agendaItem,
            'message' => 'Agenda item created successfully.',
        ], 201);
    }

    /**
     * Update an agenda item.
     */
    public function update(StoreAgendaItemRequest $request, Meeting $meeting, MeetingAgendaItem $agendaItem): JsonResponse
    {
        $agendaItem->update($request->validated());

        return response()->json([
            'data' => $agendaItem,
            'message' => 'Agenda item updated successfully.',
        ]);
    }

    /**
     * Delete an agenda item.
     */
    public function destroy(Meeting $meeting, MeetingAgendaItem $agendaItem): JsonResponse
    {
        $agendaItem->delete();

        return response()->json(['message' => 'Agenda item deleted successfully.']);
    }

    /**
     * Reorder agenda items.
     */
    public function reorder(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:meeting_agenda_items,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['items'] as $item) {
            MeetingAgendaItem::where('id', $item['id'])
                ->where('meeting_id', $meeting->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        $agendaItems = $meeting->agendaItems()->orderBy('sort_order')->get();

        return response()->json([
            'data' => $agendaItems,
            'message' => 'Agenda items reordered successfully.',
        ]);
    }
}
