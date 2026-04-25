<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHostPocket\AddAttachmentRequest;
use App\Http\Requests\LiveHostPocket\SaveRecapRequest;
use App\Models\LiveAnalytics;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Session detail / recap / upload.
 *
 * Single Inertia page that serves screen 03 (UPLOAD/RECAP) of the mockup. A
 * GET renders the session + any existing analytics + attachments, and three
 * POST/DELETE endpoints let the host save the recap (cover image, remarks,
 * timing, analytics), add attachments, and remove attachments.
 *
 * All actions guard with `live_sessions.live_host_id === auth()->user()->id`.
 * This is the correct ownership check for host-side reads: the legacy Volt
 * `sessions-show` guarded on `platformAccount->user_id` which was broken for
 * accounts shared across hosts via the `live_host_platform_account` pivot.
 */
class SessionDetailController extends Controller
{
    public function show(Request $request, LiveSession $session): Response
    {
        abort_unless($session->live_host_id === $request->user()->id, 403);

        $session->load(['platformAccount.platform', 'analytics', 'attachments']);

        return Inertia::render('SessionDetail', [
            'session' => $this->sessionDto($session),
            'analytics' => $session->analytics ? $this->analyticsDto($session->analytics) : null,
            'attachments' => $session->attachments
                ->map(fn (LiveSessionAttachment $a): array => $this->attachmentDto($a))
                ->values(),
        ]);
    }

    /**
     * Save (create or update) the session recap.
     *
     * Handles three independent concerns in one request so the mobile form
     * can post a single payload: cover image replacement, LiveSession
     * timing/remarks update, and LiveAnalytics upsert. Nothing here calls
     * `LiveSession::uploadDetails()` — that helper requires both start + end,
     * and we want to allow partial saves (e.g. jotting down viewers_peak
     * before knowing the exact end time).
     */
    public function saveRecap(SaveRecapRequest $request, LiveSession $session): RedirectResponse
    {
        abort_unless($session->live_host_id === $request->user()->id, 403);

        $data = $request->validated();

        if ($request->boolean('went_live')) {
            $this->persistWentLive($request, $session, $data);
        } else {
            $this->persistMissed($request, $session, $data);
        }

        return redirect()
            ->back(fallback: route('live-host.sessions.show', $session))
            ->with('success', $request->boolean('went_live') ? 'Recap saved.' : 'Session marked as missed.');
    }

    /**
     * Persist a "went live" recap: timings, analytics, remarks, GMV, and
     * flip status to `ended`. Proof-of-live is carried by
     * LiveSessionAttachment rows (enforced in SaveRecapRequest::withValidator).
     * Previously-captured missed-reason fields are cleared so the row stays
     * clean. `gmv_source` is always `'manual'` and `gmv_locked_at` is reset
     * to null — the PIC's verification step will lock it in a later phase.
     */
    private function persistWentLive(SaveRecapRequest $request, LiveSession $session, array $data): void
    {
        $actualStart = $data['actual_start_at'] ?? null;
        $actualEnd = $data['actual_end_at'] ?? null;

        $duration = $session->duration_minutes;
        if ($actualStart && $actualEnd) {
            $duration = (int) Carbon::parse($actualStart)->diffInMinutes(Carbon::parse($actualEnd));
        }

        $session->update([
            'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $session->remarks,
            'actual_start_at' => $actualStart ?? $session->actual_start_at,
            'actual_end_at' => $actualEnd ?? $session->actual_end_at,
            'duration_minutes' => $duration,
            'gmv_amount' => array_key_exists('gmv_amount', $data) ? $data['gmv_amount'] : $session->gmv_amount,
            'gmv_source' => 'manual',
            'gmv_locked_at' => null,
            'uploaded_at' => now(),
            'uploaded_by' => $request->user()->id,
            'status' => 'ended',
            'missed_reason_code' => null,
            'missed_reason_note' => null,
        ]);

        LiveAnalytics::updateOrCreate(
            ['live_session_id' => $session->id],
            [
                'viewers_peak' => $data['viewers_peak'] ?? 0,
                'viewers_avg' => $data['viewers_avg'] ?? 0,
                'total_likes' => $data['total_likes'] ?? 0,
                'total_comments' => $data['total_comments'] ?? 0,
                'total_shares' => $data['total_shares'] ?? 0,
                'gifts_value' => $data['gifts_value'] ?? 0,
                'duration_minutes' => $duration ?? $session->duration_minutes ?? 0,
            ]
        );
    }

    /**
     * Persist a "did not go live" recap: set status to `missed` with the
     * supplied reason code + note. Analytics and attachments are intentionally
     * left untouched so a host who flips back to "went live" doesn't lose the
     * data they already entered. Any host-supplied `gmv_amount` is ignored
     * and forced to 0 — a missed session cannot have earned GMV.
     */
    private function persistMissed(SaveRecapRequest $request, LiveSession $session, array $data): void
    {
        $session->update([
            'status' => 'missed',
            'missed_reason_code' => $data['missed_reason_code'],
            'missed_reason_note' => $data['missed_reason_note'] ?? null,
            'gmv_amount' => 0,
            'gmv_source' => 'manual',
            'gmv_locked_at' => null,
            'uploaded_at' => now(),
            'uploaded_by' => $request->user()->id,
        ]);
    }

    public function addAttachment(AddAttachmentRequest $request, LiveSession $session): RedirectResponse
    {
        abort_unless($session->live_host_id === $request->user()->id, 403);

        $file = $request->file('file');
        $path = $file->store("live-sessions/{$session->id}/attachments", 'public');

        LiveSessionAttachment::create([
            'live_session_id' => $session->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'attachment_type' => $request->string('attachment_type')->toString() ?: null,
            'description' => $request->string('description')->toString() ?: null,
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()
            ->back(fallback: route('live-host.sessions.show', $session))
            ->with('success', 'Attachment added.');
    }

    public function deleteAttachment(Request $request, LiveSession $session, LiveSessionAttachment $attachment): RedirectResponse
    {
        abort_unless($session->live_host_id === $request->user()->id, 403);
        abort_unless($attachment->live_session_id === $session->id, 404);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionDto(LiveSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'description' => $session->description,
            'status' => $session->status,
            'remarks' => $session->remarks,
            'platformAccount' => $session->platformAccount?->name,
            'platformType' => $session->platformAccount?->platform?->slug,
            'platformName' => $session->platformAccount?->platform?->name,
            'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
            'actualStartAt' => $session->actual_start_at?->toIso8601String(),
            'actualEndAt' => $session->actual_end_at?->toIso8601String(),
            'durationMinutes' => $session->duration_minutes,
            'imagePath' => $session->image_path,
            'imageUrl' => $session->image_path ? Storage::url($session->image_path) : null,
            'uploadedAt' => $session->uploaded_at?->toIso8601String(),
            'canRecap' => $session->canRecap(),
            'gmvAmount' => $session->gmv_amount !== null ? (float) $session->gmv_amount : null,
            'missedReasonCode' => $session->missed_reason_code,
            'missedReasonNote' => $session->missed_reason_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyticsDto(LiveAnalytics $analytics): array
    {
        return [
            'viewersPeak' => (int) $analytics->viewers_peak,
            'viewersAvg' => (int) $analytics->viewers_avg,
            'totalLikes' => (int) $analytics->total_likes,
            'totalComments' => (int) $analytics->total_comments,
            'totalShares' => (int) $analytics->total_shares,
            'giftsValue' => (float) $analytics->gifts_value,
            'durationMinutes' => (int) $analytics->duration_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentDto(LiveSessionAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'fileName' => $attachment->file_name,
            'filePath' => $attachment->file_path,
            'fileType' => $attachment->file_type,
            'fileSize' => (int) $attachment->file_size,
            'fileUrl' => Storage::url($attachment->file_path),
            'attachmentType' => $attachment->attachment_type,
            'description' => $attachment->description,
        ];
    }
}
