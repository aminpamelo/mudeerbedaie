<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Sessions list.
 *
 * Segmented-control tabs (Upcoming / Ended / All) over the host's own
 * `LiveSession` rows. Each DTO mirrors the session-card data shown in
 * screen 02 of docs/design-mockups/livehost-mobile-v3-grounded.html,
 * including analytics (viewers_peak, total_likes, gifts_value) for ended
 * sessions so the metrics strip can render without an extra round-trip.
 *
 * All queries are scoped to `auth()->user()->id` via `live_sessions.live_host_id`
 * so a host cannot list another host's sessions.
 */
class SessionsController extends Controller
{
    public function index(Request $request): Response
    {
        $host = $request->user();
        $filter = $request->string('filter')->toString() ?: 'ended';

        $query = LiveSession::query()
            ->with(['platformAccount.platform', 'analytics'])
            ->withCount('attachments')
            ->where('live_host_id', $host->id);

        match ($filter) {
            'upcoming' => $query
                ->whereIn('status', ['scheduled', 'live'])
                ->orderByRaw("CASE WHEN status = 'live' THEN 0
                                    WHEN status = 'scheduled' AND scheduled_start_at <= ? THEN 1
                                    ELSE 2 END", [now()])
                ->orderBy('scheduled_start_at'),
            'ended' => $query
                ->whereIn('status', ['ended', 'cancelled', 'missed'])
                ->orderByDesc('scheduled_start_at'),
            default => $query->orderByDesc('scheduled_start_at'),
        };

        $sessions = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LiveSession $session): array => $this->sessionDto($session));

        return Inertia::render('Sessions', [
            'sessions' => $sessions,
            'filter' => $filter,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionDto(LiveSession $session): array
    {
        $analytics = $session->analytics;

        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status,
            'platformAccount' => $session->platformAccount?->name,
            'platformType' => $session->platformAccount?->platform?->slug,
            'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
            'actualStartAt' => $session->actual_start_at?->toIso8601String(),
            'actualEndAt' => $session->actual_end_at?->toIso8601String(),
            'durationMinutes' => $session->duration_minutes,
            'canRecap' => $session->canRecap(),
            'missedReasonCode' => $session->missed_reason_code,
            'missedReasonNote' => $session->missed_reason_note,
            'analytics' => $analytics ? [
                'viewersPeak' => (int) $analytics->viewers_peak,
                'totalLikes' => (int) $analytics->total_likes,
                'giftsValue' => (float) $analytics->gifts_value,
            ] : null,
            'attachmentsCount' => (int) ($session->attachments_count ?? 0),
        ];
    }
}
