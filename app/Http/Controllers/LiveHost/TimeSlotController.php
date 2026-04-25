<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreTimeSlotRequest;
use App\Http\Requests\LiveHost\UpdateTimeSlotRequest;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeSlotController extends Controller
{
    public function index(Request $request): Response
    {
        $platformAccount = $request->string('platform_account')->toString();
        $dayOfWeek = $request->string('day_of_week')->toString();
        $active = $request->string('active')->toString();

        $timeSlots = LiveTimeSlot::query()
            ->with(['platformAccount.platform', 'createdBy'])
            ->when(
                $platformAccount === 'global',
                fn ($q) => $q->whereNull('platform_account_id'),
                fn ($q) => $q->when(
                    $platformAccount !== '',
                    fn ($q) => $q->where('platform_account_id', $platformAccount)
                )
            )
            ->when(
                $dayOfWeek === 'global',
                fn ($q) => $q->whereNull('day_of_week'),
                fn ($q) => $q->when(
                    $dayOfWeek !== '',
                    fn ($q) => $q->where('day_of_week', (int) $dayOfWeek)
                )
            )
            ->when(
                $active !== '',
                fn ($q) => $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('platform_account_id')
            ->orderBy('day_of_week')
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LiveTimeSlot $slot) => $this->mapTimeSlot($slot));

        return Inertia::render('time-slots/Index', [
            'timeSlots' => $timeSlots,
            'filters' => [
                'platform_account' => $platformAccount,
                'day_of_week' => $dayOfWeek,
                'active' => $active,
            ],
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('time-slots/Create', [
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function store(StoreTimeSlotRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;
        $data['status'] = $data['status'] ?? ($data['is_active'] ?? true ? 'active' : 'inactive');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        LiveTimeSlot::create($data);

        return redirect()
            ->route('livehost.time-slots.index')
            ->with('success', 'Time slot created.');
    }

    public function edit(LiveTimeSlot $timeSlot): Response
    {
        return Inertia::render('time-slots/Edit', [
            'timeSlot' => [
                'id' => $timeSlot->id,
                'platform_account_id' => $timeSlot->platform_account_id,
                'day_of_week' => $timeSlot->day_of_week,
                'start_time' => substr((string) $timeSlot->start_time, 0, 5),
                'end_time' => substr((string) $timeSlot->end_time, 0, 5),
                'is_active' => (bool) $timeSlot->is_active,
                'sort_order' => (int) $timeSlot->sort_order,
                'status' => $timeSlot->status,
            ],
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function update(UpdateTimeSlotRequest $request, LiveTimeSlot $timeSlot): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('is_active', $data) && ! isset($data['status'])) {
            $data['status'] = $data['is_active'] ? 'active' : 'inactive';
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }

        $timeSlot->update($data);

        return redirect()
            ->route('livehost.time-slots.index')
            ->with('success', 'Time slot updated.');
    }

    public function destroy(LiveTimeSlot $timeSlot): RedirectResponse
    {
        if ($timeSlot->scheduleAssignments()->exists()) {
            return back()->with(
                'error',
                'Cannot delete this time slot: it is still referenced by schedule assignments. Detach the assignments first.'
            );
        }

        $timeSlot->delete();

        return redirect()
            ->route('livehost.time-slots.index')
            ->with('success', 'Time slot deleted.');
    }

    /**
     * @return array{
     *     id: int,
     *     platformAccountId: ?int,
     *     platformAccount: ?string,
     *     platformType: ?string,
     *     dayOfWeek: ?int,
     *     dayName: ?string,
     *     startTime: string,
     *     endTime: string,
     *     durationMinutes: ?int,
     *     isActive: bool,
     *     sortOrder: int,
     *     status: ?string,
     *     createdByName: ?string,
     *     createdAt: ?string
     * }
     */
    private function mapTimeSlot(LiveTimeSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'platformAccountId' => $slot->platform_account_id,
            'platformAccount' => $slot->platformAccount?->name,
            'platformType' => $slot->platformAccount?->platform?->slug,
            'dayOfWeek' => $slot->day_of_week,
            'dayName' => $slot->day_name,
            'startTime' => substr((string) $slot->start_time, 0, 5),
            'endTime' => substr((string) $slot->end_time, 0, 5),
            'durationMinutes' => $slot->duration_minutes,
            'isActive' => (bool) $slot->is_active,
            'sortOrder' => (int) $slot->sort_order,
            'status' => $slot->status,
            'createdByName' => $slot->createdBy?->name,
            'createdAt' => $slot->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, platform: ?string}>
     */
    private function platformAccountOptions(): \Illuminate\Support\Collection
    {
        return PlatformAccount::query()
            ->with('platform:id,name,display_name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'platform_id'])
            ->map(fn (PlatformAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'platform' => $a->platform?->display_name ?? $a->platform?->name,
            ]);
    }
}
