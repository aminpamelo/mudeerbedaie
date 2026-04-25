<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\SessionReplacementRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Go Live launch pad.
 *
 * Picks one of four UI states server-side so the page has no branching
 * business logic on the client:
 *   - live     : host has a currently-live session (show end-stream controls)
 *   - imminent : next scheduled session is within [-2h, +30min] of now
 *   - upcoming : next scheduled session is further than 30min away
 *   - none     : host has no upcoming scheduled sessions
 *
 * "Imminent" intentionally looks BACK 2 hours so hosts who go live a bit
 * late (TikTok glitch, traffic) still land on the launch-pad view rather
 * than a confusing "nothing imminent" screen.
 */
class GoLiveController extends Controller
{
    /**
     * Minutes from now within which a future scheduled session is treated
     * as "imminent" (host should be preparing to stream).
     */
    private const IMMINENT_LEAD_MINUTES = 30;

    /**
     * Hours back from now during which a past scheduled session is still
     * treated as "imminent" — gives a grace window for late starts.
     */
    private const IMMINENT_GRACE_HOURS = 2;

    public function show(Request $request): Response
    {
        $host = $request->user();
        $now = now();

        $liveSession = LiveSession::query()
            ->with(['platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->where('status', 'live')
            ->orderByDesc('actual_start_at')
            ->first();

        if ($liveSession) {
            return Inertia::render('GoLive', [
                'state' => 'live',
                'session' => $this->sessionDto($liveSession),
            ]);
        }

        $candidate = LiveSession::query()
            ->with(['platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->where('status', 'scheduled')
            ->where('scheduled_start_at', '>=', $now->copy()->subHours(self::IMMINENT_GRACE_HOURS))
            ->orderBy('scheduled_start_at')
            ->first();

        if (! $candidate) {
            return Inertia::render('GoLive', [
                'state' => 'none',
                'session' => null,
            ]);
        }

        $isImminent = $candidate->scheduled_start_at->lte(
            $now->copy()->addMinutes(self::IMMINENT_LEAD_MINUTES)
        );

        return Inertia::render('GoLive', [
            'state' => $isImminent ? 'imminent' : 'upcoming',
            'session' => $this->sessionDto($candidate),
        ]);
    }

    /**
     * Start a live session for the host against the given schedule assignment.
     *
     * Creates a new `LiveSession` row with `status = live` and
     * `actual_start_at = now()`. Refuses with 422 if the slot has been taken
     * over by a replacement request — either permanently, or for today via a
     * one_date scope — and the current user is not the assigned replacement.
     * The error copy is Malay so it displays correctly to the original host.
     */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'live_schedule_assignment_id' => ['required', 'integer', 'exists:live_schedule_assignments,id'],
        ]);

        $assignmentId = (int) $data['live_schedule_assignment_id'];

        $replaced = SessionReplacementRequest::query()
            ->where('live_schedule_assignment_id', $assignmentId)
            ->where('status', SessionReplacementRequest::STATUS_ASSIGNED)
            ->where(function ($q): void {
                $q->where('scope', SessionReplacementRequest::SCOPE_PERMANENT)
                    ->orWhere(fn ($q) => $q->where('scope', SessionReplacementRequest::SCOPE_ONE_DATE)
                        ->whereDate('target_date', now()->toDateString()));
            })
            ->where('replacement_host_id', '!=', $request->user()->id)
            ->exists();

        abort_if($replaced, 422, 'Slot ini telah diganti. Sila hubungi PIC.');

        $assignment = LiveScheduleAssignment::findOrFail($assignmentId);

        $session = LiveSession::create([
            'platform_account_id' => $assignment->platform_account_id,
            'live_host_platform_account_id' => $assignment->live_host_platform_account_id,
            'live_schedule_assignment_id' => $assignment->id,
            'live_host_id' => $request->user()->id,
            'title' => 'Live Session',
            'status' => 'live',
            'scheduled_start_at' => now(),
            'actual_start_at' => now(),
            'gmv_source' => 'manual',
        ]);

        return redirect()
            ->route('live-host.sessions.show', $session)
            ->with('success', 'Session started.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionDto(LiveSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'platformAccount' => $session->platformAccount?->name,
            'platformType' => $session->platformAccount?->platform?->slug,
            'platformName' => $session->platformAccount?->platform?->display_name
                ?? $session->platformAccount?->platform?->name,
            'status' => $session->status,
            'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
            'actualStartAt' => $session->actual_start_at?->toIso8601String(),
            'durationMinutes' => $session->duration_minutes,
            'scheduleAssignmentId' => $session->live_schedule_assignment_id,
        ];
    }
}
