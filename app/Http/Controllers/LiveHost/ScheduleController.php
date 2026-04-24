<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreScheduleRequest;
use App\Http\Requests\LiveHost\UpdateScheduleRequest;
use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $search = $request->string('search')->toString();
        $host = $request->string('host')->toString();
        $platformAccount = $request->string('platform_account')->toString();
        $dayOfWeek = $request->string('day_of_week')->toString();
        $active = $request->string('active')->toString();
        $viewMode = $request->string('view')->toString() === 'calendar' ? 'calendar' : 'list';

        $baseQuery = LiveSchedule::query()
            ->with(['liveHost', 'platformAccount.platform'])
            ->when($search !== '', fn ($q) => $q->where('remarks', 'like', "%{$search}%"))
            ->when($host !== '', fn ($q) => $q->where('live_host_id', $host))
            ->when(
                $platformAccount !== '',
                fn ($q) => $q->where('platform_account_id', $platformAccount)
            )
            ->when($dayOfWeek !== '', fn ($q) => $q->where('day_of_week', (int) $dayOfWeek))
            ->when(
                $active !== '',
                fn ($q) => $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('day_of_week')
            ->orderBy('start_time');

        if ($viewMode === 'calendar') {
            $schedules = $baseQuery->get()
                ->map(fn (LiveSchedule $s) => $this->mapSchedule($s))
                ->values();
        } else {
            $schedules = $baseQuery
                ->paginate(15)
                ->withQueryString()
                ->through(fn (LiveSchedule $s) => $this->mapSchedule($s));
        }

        return Inertia::render('schedules/Index', [
            'schedules' => $schedules,
            'viewMode' => $viewMode,
            'filters' => [
                'search' => $search,
                'host' => $host,
                'platform_account' => $platformAccount,
                'day_of_week' => $dayOfWeek,
                'active' => $active,
                'view' => $viewMode,
            ],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        return Inertia::render('schedules/Create', [
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        LiveSchedule::create($data);

        return redirect()
            ->route('livehost.schedules.index')
            ->with('success', 'Schedule created.');
    }

    public function show(Request $request, LiveSchedule $schedule): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $schedule->load(['liveHost', 'platformAccount.platform', 'createdBy']);

        $recentSessions = LiveSession::query()
            ->with(['platformAccount.platform', 'liveHost'])
            ->where('platform_account_id', $schedule->platform_account_id)
            ->when(
                $schedule->live_host_id,
                fn ($q) => $q->where('live_host_id', $schedule->live_host_id)
            )
            ->latest('scheduled_start_at')
            ->take(10)
            ->get()
            ->map(fn (LiveSession $s) => [
                'id' => $s->id,
                'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
                'status' => $s->status,
                'hostName' => $s->liveHost?->name,
                'platformAccount' => $s->platformAccount?->name,
                'scheduledStart' => $s->scheduled_start_at?->toIso8601String(),
                'actualStart' => $s->actual_start_at?->toIso8601String(),
                'actualEnd' => $s->actual_end_at?->toIso8601String(),
            ]);

        return Inertia::render('schedules/Show', [
            'schedule' => array_merge(
                $this->mapSchedule($schedule),
                [
                    'createdByName' => $schedule->createdBy?->name,
                ]
            ),
            'recentSessions' => $recentSessions,
        ]);
    }

    public function edit(Request $request, LiveSchedule $schedule): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        return Inertia::render('schedules/Edit', [
            'schedule' => [
                'id' => $schedule->id,
                'platform_account_id' => $schedule->platform_account_id,
                'live_host_id' => $schedule->live_host_id,
                'day_of_week' => (int) $schedule->day_of_week,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
                'is_active' => (bool) $schedule->is_active,
                'is_recurring' => (bool) $schedule->is_recurring,
                'remarks' => $schedule->remarks,
            ],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function update(UpdateScheduleRequest $request, LiveSchedule $schedule): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $schedule->update($request->validated());

        return redirect()
            ->route('livehost.schedules.show', $schedule)
            ->with('success', 'Schedule updated.');
    }

    public function destroy(Request $request, LiveSchedule $schedule): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $schedule->delete();

        return redirect()
            ->route('livehost.schedules.index')
            ->with('success', 'Schedule deleted.');
    }

    /**
     * @return array{
     *     id: int,
     *     dayOfWeek: int,
     *     dayName: string,
     *     startTime: string,
     *     endTime: string,
     *     isActive: bool,
     *     isRecurring: bool,
     *     hostName: ?string,
     *     hostId: ?int,
     *     platformAccount: ?string,
     *     platformAccountId: ?int,
     *     platformType: ?string,
     *     remarks: ?string,
     *     createdAt: ?string
     * }
     */
    private function mapSchedule(LiveSchedule $s): array
    {
        return [
            'id' => $s->id,
            'dayOfWeek' => (int) $s->day_of_week,
            'dayName' => $s->day_name,
            'startTime' => substr((string) $s->start_time, 0, 5),
            'endTime' => substr((string) $s->end_time, 0, 5),
            'isActive' => (bool) $s->is_active,
            'isRecurring' => (bool) $s->is_recurring,
            'hostName' => $s->liveHost?->name,
            'hostId' => $s->live_host_id,
            'platformAccount' => $s->platformAccount?->name,
            'platformAccountId' => $s->platform_account_id,
            'platformType' => $s->platformAccount?->platform?->slug,
            'remarks' => $s->remarks,
            'createdAt' => $s->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    private function hostOptions(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);
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
