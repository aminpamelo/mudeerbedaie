<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveTimeSlotOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-creator slot overrides — a date-ranged set of slots that replaces a live
 * account's normal weekly slots on the Session Slots calendar. Slots are stored
 * as live_time_slots rows tagged with the override id (so assignments can still
 * reference them by time_slot_id).
 */
class SlotOverrideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $liveAccountId = $request->integer('live_account');

        $overrides = LiveTimeSlotOverride::query()
            ->where('live_account_id', $liveAccountId)
            ->with(['slots' => fn ($q) => $q->orderBy('day_of_week')->orderBy('start_time')])
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (LiveTimeSlotOverride $o) => $this->serialize($o));

        return response()->json(['overrides' => $overrides]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);

        $override = LiveTimeSlotOverride::create([
            'live_account_id' => $data['live_account_id'],
            'effective_from' => $data['effective_from'],
            'effective_until' => $data['effective_until'] ?? null,
            'label' => $data['label'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->syncSlots($override, $data['slots']);

        return response()->json(['override' => $this->serialize($override->fresh('slots'))]);
    }

    public function update(Request $request, LiveTimeSlotOverride $slotOverride): JsonResponse
    {
        $data = $this->validated($request, false);

        $slotOverride->update([
            'effective_from' => $data['effective_from'],
            'effective_until' => $data['effective_until'] ?? null,
            'label' => $data['label'] ?? null,
        ]);

        $this->syncSlots($slotOverride, $data['slots']);

        return response()->json(['override' => $this->serialize($slotOverride->fresh('slots'))]);
    }

    public function destroy(LiveTimeSlotOverride $slotOverride): JsonResponse
    {
        $slotOverride->delete(); // cascade removes its slots

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireAccount): array
    {
        return $request->validate([
            'live_account_id' => [$requireAccount ? 'required' : 'sometimes', 'exists:live_accounts,id'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'label' => ['nullable', 'string', 'max:120'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i'],
        ]);
    }

    /**
     * @param  array<int, array{day_of_week: int, start_time: string, end_time: string}>  $slots
     */
    private function syncSlots(LiveTimeSlotOverride $override, array $slots): void
    {
        $override->slots()->delete(); // replace the whole set

        foreach (array_values($slots) as $i => $slot) {
            $override->slots()->create([
                'day_of_week' => $slot['day_of_week'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'is_active' => true,
                'sort_order' => $i,
                'created_by' => $override->created_by,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(LiveTimeSlotOverride $override): array
    {
        return [
            'id' => $override->id,
            'live_account_id' => $override->live_account_id,
            'label' => $override->label,
            'effective_from' => $override->effective_from->toDateString(),
            'effective_until' => $override->effective_until?->toDateString(),
            'slots' => $override->slots->map(fn ($s) => [
                'id' => $s->id,
                'day_of_week' => (int) $s->day_of_week,
                'start_time' => substr((string) $s->start_time, 0, 5),
                'end_time' => substr((string) $s->end_time, 0, 5),
            ])->values(),
        ];
    }
}
