<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveSlotTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reusable, global slot templates — a named weekly grid of time windows a PIC can
 * apply to a slot override in one pick. Managed on a dedicated page; the calendar
 * hands the list to the override modal so applying is a client-side fill.
 */
class SlotTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('slot-templates/Index', [
            'templates' => LiveSlotTemplate::query()
                ->with('createdBy:id,name')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (LiveSlotTemplate $t) => $this->serialize($t)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        LiveSlotTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slots' => $this->normaliseSlots($data['slots']),
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Slot template created.');
    }

    public function update(Request $request, LiveSlotTemplate $slotTemplate): RedirectResponse
    {
        $data = $this->validated($request);

        $slotTemplate->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slots' => $this->normaliseSlots($data['slots']),
            'is_active' => $data['is_active'] ?? $slotTemplate->is_active,
        ]);

        return back()->with('success', 'Slot template updated.');
    }

    public function destroy(LiveSlotTemplate $slotTemplate): RedirectResponse
    {
        $slotTemplate->delete();

        return back()->with('success', 'Slot template deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i', 'after:slots.*.start_time'],
        ], [
            'slots.required' => 'Add at least one time window.',
            'slots.*.end_time.after' => 'End time must be later than the start time.',
        ]);
    }

    /**
     * Keep only the blueprint keys and stable "HH:MM" times, ordered by day then
     * start — so a stored template is clean regardless of the client's row order.
     *
     * @param  array<int, array{day_of_week: int|string, start_time: string, end_time: string}>  $slots
     * @return array<int, array{day_of_week: int, start_time: string, end_time: string}>
     */
    private function normaliseSlots(array $slots): array
    {
        return collect($slots)
            ->map(fn (array $s) => [
                'day_of_week' => (int) $s['day_of_week'],
                'start_time' => substr((string) $s['start_time'], 0, 5),
                'end_time' => substr((string) $s['end_time'], 0, 5),
            ])
            ->sort(fn (array $a, array $b) => [$a['day_of_week'], $a['start_time']] <=> [$b['day_of_week'], $b['start_time']])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(LiveSlotTemplate $template): array
    {
        $slots = collect($template->slots ?? [])->map(fn ($s) => [
            'day_of_week' => (int) $s['day_of_week'],
            'start_time' => substr((string) $s['start_time'], 0, 5),
            'end_time' => substr((string) $s['end_time'], 0, 5),
        ])->values();

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'isActive' => (bool) $template->is_active,
            'slots' => $slots,
            'slotCount' => $slots->count(),
            'dayCount' => $slots->pluck('day_of_week')->unique()->count(),
            'createdByName' => $template->createdBy?->name,
            'updatedAt' => $template->updated_at?->toIso8601String(),
        ];
    }
}
