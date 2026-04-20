<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => $this->stats(),
            'liveNow' => $this->liveNow(),
            'upcoming' => $this->upcoming(),
            'recentActivity' => $this->recentActivity(),
            'topHosts' => $this->topHosts(),
        ]);
    }

    /**
     * JSON endpoint used by the Dashboard page to poll the "live now" counters
     * every ~10 seconds without a full Inertia reload.
     */
    public function liveNowJson(Request $request)
    {
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
