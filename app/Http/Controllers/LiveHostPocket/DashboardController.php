<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Today screen.
 *
 * Renders the host-scoped Today aggregation (live-now card, today stats,
 * up-next list). All queries are scoped to `auth()->user()->id` via the
 * `live_host_id` column on `live_sessions` so one host cannot see another
 * host's data. The legacy `platform_accounts.user_id` guard (still used on
 * the Volt sessions-show page) is intentionally bypassed here — the
 * `live_host_platform_account` pivot + `live_sessions.live_host_id` are
 * authoritative for host-side queries.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $host = $request->user();

        $liveNow = LiveSession::query()
            ->with(['platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->where('status', 'live')
            ->orderByDesc('actual_start_at')
            ->get()
            ->map(fn (LiveSession $session): array => $this->liveSessionDto($session));

        $sessionsToday = LiveSession::query()
            ->where('live_host_id', $host->id)
            ->whereDate('scheduled_start_at', today())
            ->count();

        $sessionsDoneToday = LiveSession::query()
            ->where('live_host_id', $host->id)
            ->where('status', 'ended')
            ->whereDate('actual_end_at', today())
            ->count();

        $watchMinutesToday = (int) LiveSession::query()
            ->where('live_host_id', $host->id)
            ->where('status', 'ended')
            ->whereDate('actual_end_at', today())
            ->sum('duration_minutes');

        $upcoming = LiveSession::query()
            ->with(['platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->where('status', 'scheduled')
            ->where('scheduled_start_at', '>', now())
            ->orderBy('scheduled_start_at')
            ->take(2)
            ->get()
            ->map(fn (LiveSession $session): array => $this->upcomingDto($session));

        return Inertia::render('Today', [
            'liveNow' => $liveNow,
            'stats' => [
                'sessionsToday' => $sessionsToday,
                'sessionsDoneToday' => $sessionsDoneToday,
                'watchMinutesToday' => $watchMinutesToday,
            ],
            'upcoming' => $upcoming,
        ]);
    }

    /**
     * Transition a host's live session from `live` to `ended`.
     *
     * Stamps `actual_end_at = now()` and recomputes `duration_minutes` from
     * `actual_start_at → now()`. Refuses (403) if the session belongs to a
     * different host, and (409) if the session is not currently `live`.
     */
    public function endSession(Request $request, LiveSession $session): RedirectResponse
    {
        abort_unless($session->live_host_id === $request->user()->id, 403);
        abort_unless($session->status === 'live', 409, 'Session is not currently live.');

        $session->update([
            'status' => 'ended',
            'actual_end_at' => now(),
            'duration_minutes' => $session->actual_start_at
                ? (int) $session->actual_start_at->diffInMinutes(now())
                : $session->duration_minutes,
        ]);

        return redirect()->route('live-host.dashboard')->with('success', 'Session ended.');
    }

    /**
     * @return array<string, mixed>
     */
    private function liveSessionDto(LiveSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'platformAccount' => $session->platformAccount?->name,
            'platformType' => $session->platformAccount?->platform?->slug,
            'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
            'scheduledEndAt' => $session->scheduled_start_at && $session->duration_minutes
                ? $session->scheduled_start_at->copy()->addMinutes($session->duration_minutes)->toIso8601String()
                : null,
            'actualStartAt' => $session->actual_start_at?->toIso8601String(),
            'durationMinutes' => $session->duration_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function upcomingDto(LiveSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'platformAccount' => $session->platformAccount?->name,
            'platformType' => $session->platformAccount?->platform?->slug,
            'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
            'durationMinutes' => $session->duration_minutes,
        ];
    }
}
