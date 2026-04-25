<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveSchedule;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        if ($request->user()?->isLiveHostAssistant()) {
            return Inertia::render('SchedulerDashboard', $this->schedulerStats());
        }

        return Inertia::render('Dashboard', [
            'stats' => $this->stats(),
            'liveNow' => $this->liveNow(),
            'upcoming' => $this->upcoming(),
            'recentActivity' => $this->recentActivity(),
            'topHosts' => $this->topHosts(),
            'pendingReplacements' => SessionReplacementRequest::query()
                ->where('status', SessionReplacementRequest::STATUS_PENDING)
                ->count(),
        ]);
    }

    /**
     * JSON endpoint used by the Dashboard page to poll the "live now" counters
     * every ~10 seconds without a full Inertia reload.
     */
    public function liveNowJson(Request $request)
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        return response()->json([
            'liveNow' => $this->liveNow(),
            'stats' => [
                'liveNow' => LiveSession::where('status', 'live')->count(),
                'totalHosts' => User::where('role', 'live_host')->count(),
                'activeHosts' => User::where('role', 'live_host')->where('status', 'active')->count(),
            ],
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function stats(): array
    {
        return [
            'totalHosts' => User::where('role', 'live_host')->count(),
            'activeHosts' => User::where('role', 'live_host')->where('status', 'active')->count(),
            'platformAccounts' => PlatformAccount::count(),
            'liveNow' => LiveSession::where('status', 'live')->count(),
            'sessionsToday' => LiveSession::whereDate('scheduled_start_at', today())->count(),
            'watchHoursToday' => $this->watchHoursToday(),
        ];
    }

    /**
     * Props for the scheduler-focused dashboard. No GMV, commission, or
     * financial fields — the livehost_assistant role must not see revenue.
     *
     * @return array<string, mixed>
     */
    private function schedulerStats(): array
    {
        $weekStart = \Carbon\CarbonImmutable::now()->startOfWeek(\Carbon\CarbonImmutable::SUNDAY);
        $weekEnd = $weekStart->endOfWeek(\Carbon\CarbonImmutable::SATURDAY);
        $today = today();

        $weekSlots = LiveScheduleAssignment::query()
            ->where('is_template', false)
            ->whereBetween('schedule_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get(['id', 'schedule_date', 'live_host_id', 'platform_account_id', 'status']);

        $assignedCount = $weekSlots->whereNotNull('live_host_id')->count();
        $totalCount = $weekSlots->count();

        $todaySlots = LiveScheduleAssignment::query()
            ->where('is_template', false)
            ->whereDate('schedule_date', $today)
            ->with(['liveHost:id,name', 'platformAccount:id,name'])
            ->orderBy('schedule_date')
            ->orderBy('id')
            ->get(['id', 'schedule_date', 'live_host_id', 'platform_account_id', 'status']);

        return [
            'stats' => [
                'coveragePercent' => $totalCount === 0 ? 0 : (int) round($assignedCount / $totalCount * 100),
                'unassignedCount' => $totalCount - $assignedCount,
                'activeHosts' => User::query()->where('role', 'live_host')->where('status', 'active')->count(),
                'platformAccounts' => PlatformAccount::query()->count(),
            ],
            'unassignedThisWeek' => $weekSlots
                ->whereNull('live_host_id')
                ->take(20)
                ->values()
                ->map(fn (LiveScheduleAssignment $s) => [
                    'id' => $s->id,
                    'schedule_date' => $s->schedule_date?->toDateString(),
                    'platform_account_id' => $s->platform_account_id,
                    'status' => $s->status,
                ]),
            'todaySlots' => $todaySlots->map(fn (LiveScheduleAssignment $s) => [
                'id' => $s->id,
                'schedule_date' => $s->schedule_date?->toDateString(),
                'status' => $s->status,
                'host_name' => $s->liveHost?->name,
                'platform_account_label' => $s->platformAccount?->name,
            ]),
        ];
    }

    private function liveNow(): Collection
    {
        return LiveSession::query()
            ->with(['platformAccount.platform', 'liveHost'])
            ->where('status', 'live')
            ->orderByDesc('actual_start_at')
            ->take(5)
            ->get()
            ->map(fn (LiveSession $s) => [
                'id' => $s->id,
                'hostName' => $s->liveHost?->name,
                'initials' => $this->initials($s->liveHost?->name),
                'platformAccount' => $s->platformAccount?->name,
                'platformType' => $s->platformAccount?->platform?->slug,
                'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
                'startedAt' => $s->actual_start_at?->toIso8601String(),
                'viewers' => 0,
            ]);
    }

    private function upcoming(): Collection
    {
        return LiveSchedule::query()
            ->with(['platformAccount.platform', 'liveHost'])
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->take(6)
            ->get()
            ->map(fn (LiveSchedule $s) => [
                'id' => $s->id,
                'dayOfWeek' => $s->day_of_week,
                'dayName' => $s->day_name,
                'startTime' => $s->start_time,
                'endTime' => $s->end_time,
                'hostName' => $s->liveHost?->name,
                'platformAccount' => $s->platformAccount?->name,
                'platformType' => $s->platformAccount?->platform?->slug,
                'isRecurring' => (bool) $s->is_recurring,
            ]);
    }

    private function recentActivity(): Collection
    {
        return LiveSession::query()
            ->with(['liveHost', 'platformAccount'])
            ->latest('updated_at')
            ->take(10)
            ->get()
            ->map(fn (LiveSession $s) => [
                'id' => $s->id,
                'kind' => $s->status,
                'hostName' => $s->liveHost?->name,
                'platformAccount' => $s->platformAccount?->name,
                'at' => $s->updated_at?->toIso8601String(),
            ]);
    }

    private function topHosts(): Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->withCount('platformAccounts')
            ->selectSub(
                LiveSession::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('live_sessions.live_host_id', 'users.id'),
                'hosted_sessions_count'
            )
            ->orderByDesc('hosted_sessions_count')
            ->take(5)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $this->initials($u->name),
                'accounts' => (int) $u->platform_accounts_count,
                'sessions' => (int) ($u->hosted_sessions_count ?? 0),
                'status' => $u->status,
            ]);
    }

    private function watchHoursToday(): float
    {
        $today = today();
        $sessions = LiveSession::query()
            ->whereDate('actual_start_at', $today)
            ->whereNotNull('actual_end_at')
            ->get(['actual_start_at', 'actual_end_at']);

        $minutes = $sessions->sum(fn (LiveSession $s) => $s->actual_start_at && $s->actual_end_at
            ? $s->actual_start_at->diffInMinutes($s->actual_end_at)
            : 0);

        return round($minutes / 60, 1);
    }

    private function initials(?string $name): string
    {
        if (! $name) {
            return '??';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(mb_substr(($parts[0] ?? '').($parts[1] ?? ''), 0, 2, 'UTF-8'), 'UTF-8');
    }
}
