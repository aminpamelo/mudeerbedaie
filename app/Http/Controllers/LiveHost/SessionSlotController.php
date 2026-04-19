<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreSessionSlotRequest;
use App\Http\Requests\LiveHost\UpdateSessionSlotRequest;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionSlotController extends Controller
{
    public function index(Request $request): Response
    {
        $host = $request->string('host')->toString();
        $platformAccount = $request->string('platform_account')->toString();
        $status = $request->string('status')->toString();
        $dayOfWeek = $request->string('day_of_week')->toString();
        $mode = $request->string('mode')->toString();
        $scheduleDate = $request->string('schedule_date')->toString();

        $assignments = LiveScheduleAssignment::query()
            ->with([
                'liveHost:id,name,email',
                'platformAccount:id,name,platform_id',
                'platformAccount.platform:id,name,display_name,slug',
                'timeSlot:id,start_time,end_time,day_of_week,platform_account_id',
                'createdBy:id,name',
            ])
            ->when(
                $host === 'unassigned',
                fn ($q) => $q->whereNull('live_host_id'),
                fn ($q) => $q->when($host !== '', fn ($q) => $q->where('live_host_id', $host))
            )
            ->when(
                $platformAccount !== '',
                fn ($q) => $q->where('platform_account_id', $platformAccount)
            )
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($dayOfWeek !== '', fn ($q) => $q->where('day_of_week', (int) $dayOfWeek))
            ->when(
                $mode === 'template',
                fn ($q) => $q->where('is_template', true),
                fn ($q) => $q->when(
                    $mode === 'dated',
                    fn ($q) => $q->where('is_template', false)
                )
            )
            ->when($scheduleDate !== '', fn ($q) => $q->where('schedule_date', $scheduleDate))
            ->orderByDesc('is_template')
            ->orderBy('day_of_week')
            ->orderBy('schedule_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LiveScheduleAssignment $a) => $this->mapAssignment($a));

        return Inertia::render('session-slots/Index', [
            'sessionSlots' => $assignments,
            'filters' => [
                'host' => $host,
                'platform_account' => $platformAccount,
                'status' => $status,
                'day_of_week' => $dayOfWeek,
                'mode' => $mode,
                'schedule_date' => $scheduleDate,
            ],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('session-slots/Create', [
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
            'timeSlots' => $this->timeSlotOptions(),
        ]);
    }

    public function store(StoreSessionSlotRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;
        $data['status'] = $data['status'] ?? 'scheduled';

        LiveScheduleAssignment::create($data);

        return redirect()
            ->route('livehost.session-slots.index')
            ->with('success', 'Session slot created.');
    }

    public function show(LiveScheduleAssignment $sessionSlot): Response
    {
        $sessionSlot->load([
            'liveHost:id,name,email',
            'platformAccount:id,name,platform_id',
            'platformAccount.platform:id,name,display_name,slug',
            'timeSlot:id,start_time,end_time,day_of_week,platform_account_id',
            'createdBy:id,name',
        ]);

        return Inertia::render('session-slots/Show', [
            'sessionSlot' => $this->mapAssignment($sessionSlot),
        ]);
    }

    public function edit(LiveScheduleAssignment $sessionSlot): Response
    {
        return Inertia::render('session-slots/Edit', [
            'sessionSlot' => [
                'id' => $sessionSlot->id,
                'platform_account_id' => $sessionSlot->platform_account_id,
                'time_slot_id' => $sessionSlot->time_slot_id,
                'live_host_id' => $sessionSlot->live_host_id,
                'day_of_week' => (int) $sessionSlot->day_of_week,
                'schedule_date' => $sessionSlot->schedule_date?->format('Y-m-d'),
                'is_template' => (bool) $sessionSlot->is_template,
                'status' => $sessionSlot->status,
                'remarks' => $sessionSlot->remarks,
            ],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
            'timeSlots' => $this->timeSlotOptions(),
        ]);
    }

    public function update(UpdateSessionSlotRequest $request, LiveScheduleAssignment $sessionSlot): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? $sessionSlot->status;

        $sessionSlot->update($data);

        return redirect()
            ->route('livehost.session-slots.show', $sessionSlot)
            ->with('success', 'Session slot updated.');
    }

    public function destroy(LiveScheduleAssignment $sessionSlot): RedirectResponse
    {
        $sessionSlot->delete();

        return redirect()
            ->route('livehost.session-slots.index')
            ->with('success', 'Session slot deleted.');
    }

    /**
     * @return array{
     *     id: int,
     *     platformAccountId: int,
     *     platformAccount: ?string,
     *     platformType: ?string,
     *     timeSlotId: int,
     *     timeSlotLabel: string,
     *     startTime: ?string,
     *     endTime: ?string,
     *     hostId: ?int,
     *     hostName: ?string,
     *     hostEmail: ?string,
     *     dayOfWeek: int,
     *     dayName: string,
     *     scheduleDate: ?string,
     *     isTemplate: bool,
     *     status: ?string,
     *     statusColor: ?string,
     *     remarks: ?string,
     *     createdByName: ?string,
     *     createdAt: ?string,
     *     updatedAt: ?string
     * }
     */
    private function mapAssignment(LiveScheduleAssignment $a): array
    {
        $start = $a->timeSlot ? substr((string) $a->timeSlot->start_time, 0, 5) : null;
        $end = $a->timeSlot ? substr((string) $a->timeSlot->end_time, 0, 5) : null;
        $timeSlotLabel = $start && $end ? "{$start}–{$end}" : '—';

        return [
            'id' => $a->id,
            'platformAccountId' => $a->platform_account_id,
            'platformAccount' => $a->platformAccount?->name,
            'platformType' => $a->platformAccount?->platform?->slug,
            'timeSlotId' => $a->time_slot_id,
            'timeSlotLabel' => $timeSlotLabel,
            'startTime' => $start,
            'endTime' => $end,
            'hostId' => $a->live_host_id,
            'hostName' => $a->liveHost?->name,
            'hostEmail' => $a->liveHost?->email,
            'dayOfWeek' => (int) $a->day_of_week,
            'dayName' => $a->day_name_en,
            'scheduleDate' => $a->schedule_date?->format('Y-m-d'),
            'isTemplate' => (bool) $a->is_template,
            'status' => $a->status,
            'statusColor' => $a->status_color,
            'remarks' => $a->remarks,
            'createdByName' => $a->createdBy?->name,
            'createdAt' => $a->created_at?->toIso8601String(),
            'updatedAt' => $a->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, email: ?string}>
     */
    private function hostOptions(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
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

    /**
     * @return \Illuminate\Support\Collection<int, array{
     *     id: int,
     *     label: string,
     *     dayOfWeek: ?int,
     *     platformAccountId: ?int,
     *     startTime: string,
     *     endTime: string
     * }>
     */
    private function timeSlotOptions(): \Illuminate\Support\Collection
    {
        return LiveTimeSlot::query()
            ->where('is_active', true)
            ->orderBy('platform_account_id')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get(['id', 'platform_account_id', 'day_of_week', 'start_time', 'end_time'])
            ->map(function (LiveTimeSlot $slot) {
                $start = substr((string) $slot->start_time, 0, 5);
                $end = substr((string) $slot->end_time, 0, 5);

                return [
                    'id' => $slot->id,
                    'label' => "{$start}–{$end}",
                    'dayOfWeek' => $slot->day_of_week !== null ? (int) $slot->day_of_week : null,
                    'platformAccountId' => $slot->platform_account_id,
                    'startTime' => $start,
                    'endTime' => $end,
                ];
            });
    }
}
