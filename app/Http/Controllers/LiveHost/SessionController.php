<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreLiveSessionAttachmentRequest;
use App\Http\Requests\LiveHost\UpdateLiveSessionRequest;
use App\Http\Requests\LiveHost\VerifyLinkLiveSessionRequest;
use App\Http\Requests\LiveHost\VerifyLiveSessionRequest;
use App\Models\ActualLiveRecord;
use App\Models\LiveAnalytics;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\LiveSessionVerificationEvent;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\ActualLiveRecordCandidateFinder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $tab = $this->resolveTab($request->string('tab')->toString());
        $status = $request->string('status')->toString();
        $platformAccount = $request->string('platform_account')->toString();
        $host = $request->string('host')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $search = trim($request->string('search')->toString());

        // Session IDs are displayed as "LS-00626" (a zero-padded id). Normalise
        // whatever the user typed down to the numeric id so any of "626",
        // "00626", "LS-626" or "LS-00626" resolves to the same lookup.
        $searchId = ltrim((string) preg_replace('/\D/', '', $search), '0');

        // Filters that apply to every tab — used both by the paginated query
        // and by the per-tab count query so the badges reflect the current
        // platform/host/date scope.
        $applyCommonFilters = function ($q) use ($platformAccount, $host, $status, $from, $to, $searchId) {
            return $q
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->when(
                    $platformAccount !== '',
                    fn ($q) => $q->where('platform_account_id', $platformAccount)
                )
                ->when($host !== '', fn ($q) => $q->where('live_host_id', $host))
                ->when($from !== '', fn ($q) => $q->whereDate('scheduled_start_at', '>=', $from))
                ->when($to !== '', fn ($q) => $q->whereDate('scheduled_start_at', '<=', $to))
                ->when($searchId !== '', fn ($q) => $q->where('id', 'like', '%'.$searchId.'%'));
        };

        $sessions = LiveSession::query()
            ->with([
                'platformAccount:id,name,platform_id',
                'platformAccount.platform:id,name,display_name,slug',
                'liveHost:id,name,email',
                'verifiedBy:id,name',
                'analytics',
                'attachments.uploader:id,name',
            ])
            ->tap($applyCommonFilters)
            ->tap(fn ($q) => $this->applyTabFilter($q, $tab))
            ->orderByDesc('scheduled_start_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LiveSession $s) => $this->mapSession($s));

        return Inertia::render('sessions/Index', [
            'sessions' => $sessions,
            'filters' => [
                'tab' => $tab,
                'status' => $status,
                'platform_account' => $platformAccount,
                'host' => $host,
                'from' => $from,
                'to' => $to,
                'search' => $search,
            ],
            'tabCounts' => $this->tabCounts($applyCommonFilters),
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    /**
     * @return 'all'|'needs_review'|'verified'|'rejected'
     */
    private function resolveTab(string $tab): string
    {
        return in_array($tab, ['all', 'needs_review', 'verified', 'rejected'], true)
            ? $tab
            : 'all';
    }

    /**
     * Apply the "bucket" filter on top of the shared filters. "Needs review"
     * means a host-submitted recap (status=ended) that the PIC hasn't yet
     * signed off on.
     */
    private function applyTabFilter(Builder $query, string $tab): void
    {
        match ($tab) {
            'needs_review' => $query
                ->where('status', 'ended')
                ->where('verification_status', 'pending'),
            'verified' => $query->where('verification_status', 'verified'),
            'rejected' => $query->where('verification_status', 'rejected'),
            default => null,
        };
    }

    /**
     * Per-tab counts used for the sidebar-style badges on the tab strip.
     * Shares the common filters so the numbers match what the user would
     * see if they clicked into the tab.
     *
     * @return array{all: int, needs_review: int, verified: int, rejected: int}
     */
    private function tabCounts(\Closure $applyCommonFilters): array
    {
        $base = fn () => LiveSession::query()->tap($applyCommonFilters);

        return [
            'all' => $base()->count(),
            'needs_review' => $base()
                ->where('status', 'ended')
                ->where('verification_status', 'pending')
                ->count(),
            'verified' => $base()->where('verification_status', 'verified')->count(),
            'rejected' => $base()->where('verification_status', 'rejected')->count(),
        ];
    }

    public function update(UpdateLiveSessionRequest $request, LiveSession $session): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $data = $request->validated();
        $analytics = $data['analytics'] ?? null;
        unset($data['analytics']);

        $session->update($data);

        if (is_array($analytics) && $analytics !== []) {
            LiveAnalytics::updateOrCreate(
                ['live_session_id' => $session->id],
                [
                    'viewers_peak' => $analytics['viewers_peak'] ?? 0,
                    'viewers_avg' => $analytics['viewers_avg'] ?? 0,
                    'total_likes' => $analytics['total_likes'] ?? 0,
                    'total_comments' => $analytics['total_comments'] ?? 0,
                    'total_shares' => $analytics['total_shares'] ?? 0,
                    'gifts_value' => $analytics['gifts_value'] ?? 0,
                    'duration_minutes' => $session->duration_minutes ?? 0,
                ]
            );
        }

        return back()->with('success', 'Session updated.');
    }

    public function verify(VerifyLiveSessionRequest $request, LiveSession $session): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $data = $request->validated();
        $nextStatus = $data['verification_status'];

        // NEW GATE: verified status must go through verify-link
        if ($nextStatus === 'verified') {
            abort(422, 'Use verify-link with an actual_live_record_id.');
        }

        $isUnverify = $nextStatus === 'pending';
        $wasVerified = $session->verification_status === 'verified';

        // Unverifying rewrites GMV to 0 — block it if a locked payroll run already
        // paid the host on this session.
        if ($isUnverify && $this->isSessionPayrollLocked($session)) {
            return back()->withErrors([
                'verification' => 'A locked payroll run covers this session — unlock it before unverifying.',
            ])->setStatusCode(423);
        }

        $attributes = [
            'verification_status' => $nextStatus,
            'verification_notes' => $data['verification_notes'] ?? null,
            'verified_by' => $isUnverify ? null : $request->user()?->id,
            'verified_at' => $isUnverify ? null : now(),
        ];

        // Unverify: also clear the link + GMV lock
        if ($isUnverify) {
            $attributes['matched_actual_live_record_id'] = null;
            $attributes['gmv_amount'] = 0;
            $attributes['gmv_source'] = null;
            $attributes['gmv_locked_at'] = null;
        }

        $priorRecordId = $session->matched_actual_live_record_id;
        $priorGmv = (float) ($session->gmv_amount ?? 0);

        DB::transaction(function () use ($session, $attributes, $nextStatus, $wasVerified, $priorRecordId, $priorGmv, $request, $data, $isUnverify) {
            // Unverify detaches every attributed record so re-verify starts clean.
            if ($isUnverify) {
                $session->actualLiveRecords()->detach();
            }

            $session->update($attributes);

            $action = match ($nextStatus) {
                'rejected' => 'reject',
                'pending' => 'unverify',
                default => null,
            };

            if ($action !== null) {
                LiveSessionVerificationEvent::create([
                    'live_session_id' => $session->id,
                    'actual_live_record_id' => $action === 'unverify' ? $priorRecordId : null,
                    'action' => $action,
                    'user_id' => $request->user()?->id,
                    'gmv_snapshot' => $wasVerified ? $priorGmv : 0,
                    'notes' => $data['verification_notes'] ?? null,
                ]);
            }
        });

        $flash = match ($nextStatus) {
            'rejected' => 'Session rejected.',
            'pending' => 'Verification reset.',
            default => 'Session updated.',
        };

        return back()->with('success', $flash);
    }

    /**
     * JSON endpoint feeding the quick-verify modal on the Live Sessions list.
     * Returns the same candidate shape as the Show page so the modal can
     * require a record selection before calling verify-link.
     */
    public function candidates(Request $request, LiveSession $session): JsonResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $candidates = $this->buildCandidatePayload($session);

        return response()->json([
            'candidates' => $candidates,
        ]);
    }

    /**
     * Candidate TikTok records for a session, with every record of the nearest
     * contiguous split-live cluster flagged isSuggested (so the verify modal can
     * pre-select the whole split). Shared by candidates() and show().
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCandidatePayload(LiveSession $session): array
    {
        $finder = app(ActualLiveRecordCandidateFinder::class);
        $models = $finder->forSession($session);
        $clusterIds = $finder->suggestedClusterIds($models, $session);

        return $models->map(fn (ActualLiveRecord $r) => [
            'id' => $r->id,
            'launchedTime' => $r->launched_time?->toIso8601String(),
            'endedTime' => $r->ended_time?->toIso8601String(),
            'durationSeconds' => $r->duration_seconds,
            'gmvMyr' => (float) $r->gmv_myr,
            'liveAttributedGmvMyr' => (float) $r->live_attributed_gmv_myr,
            'viewers' => $r->viewers,
            'itemsSold' => $r->items_sold,
            'creatorHandle' => $r->creator_handle,
            'source' => $r->source,
            'isSuggested' => in_array($r->id, $clusterIds, true),
        ])->values()->all();
    }

    public function verifyLink(VerifyLinkLiveSessionRequest $request, LiveSession $session): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        if ($session->verification_status !== 'pending') {
            return back()->withErrors([
                'verification' => 'Session is not pending verification.',
            ]);
        }

        $ids = $request->validated()['actual_live_record_id'];
        $records = ActualLiveRecord::whereIn('id', $ids)->get();

        // Every linked record must belong to this session's shop.
        if ($records->contains(fn (ActualLiveRecord $r) => $r->platform_account_id !== $session->platform_account_id)) {
            return back()->withErrors([
                'actual_live_record_id' => 'One or more records do not belong to this platform account.',
            ])->setStatusCode(422);
        }

        // Global guard (mirrors the pivot's UNIQUE): a record already attributed
        // to another session cannot be double-counted here. Check the pivot and
        // the retained primary pointer.
        $takenElsewhere = DB::table('live_session_actual_live_record')
            ->whereIn('actual_live_record_id', $ids)
            ->where('live_session_id', '!=', $session->id)
            ->exists()
            || LiveSession::query()
                ->whereIn('matched_actual_live_record_id', $ids)
                ->where('id', '!=', $session->id)
                ->exists();

        if ($takenElsewhere) {
            return back()->withErrors([
                'actual_live_record_id' => 'One or more records are already linked to another session.',
            ])->setStatusCode(409);
        }

        // Don't let a link change rewrite GMV a host was already paid on.
        if ($this->isSessionPayrollLocked($session)) {
            return back()->withErrors([
                'actual_live_record_id' => 'A locked payroll run covers this session — unlock it before re-linking.',
            ])->setStatusCode(423);
        }

        try {
            DB::transaction(function () use ($session, $records, $request) {
                $primaryId = $records->sortBy('launched_time')->first()->id;
                $userId = $request->user()->id;

                // Sum of live-attributed GMV across every linked record. Because
                // each record can belong to only one session (pivot UNIQUE), this
                // figure is counted exactly once in payroll.
                $summedGmv = $records->sum(fn (ActualLiveRecord $r) => max(0.0, (float) $r->live_attributed_gmv_myr));

                $session->actualLiveRecords()->sync(
                    $records->mapWithKeys(fn (ActualLiveRecord $r) => [
                        $r->id => [
                            'is_primary' => $r->id === $primaryId,
                            'live_attributed_gmv_myr' => max(0.0, (float) $r->live_attributed_gmv_myr),
                            'linked_by' => $userId,
                            'linked_at' => now(),
                        ],
                    ])->all()
                );

                $session->update([
                    'matched_actual_live_record_id' => $primaryId,
                    'gmv_amount' => $summedGmv,
                    'gmv_source' => 'tiktok_actual',
                    'gmv_locked_at' => now(),
                    'verification_status' => 'verified',
                    'verified_by' => $userId,
                    'verified_at' => now(),
                ]);

                // Aggregate the split's stats onto the session's analytics row:
                // peak viewers = MAX across segments, engagement + duration = SUM.
                LiveAnalytics::updateOrCreate(
                    ['live_session_id' => $session->id],
                    [
                        'viewers_peak' => (int) $records->max('viewers'),
                        'viewers_avg' => (int) $records->max('viewers'),
                        'total_likes' => (int) $records->sum('likes'),
                        'total_comments' => (int) $records->sum('comments'),
                        'total_shares' => (int) $records->sum('shares'),
                        'duration_minutes' => (int) round($records->sum('duration_seconds') / 60),
                    ]
                );

                foreach ($records as $r) {
                    LiveSessionVerificationEvent::create([
                        'live_session_id' => $session->id,
                        'actual_live_record_id' => $r->id,
                        'action' => 'verify_link',
                        'user_id' => $userId,
                        'gmv_snapshot' => max(0.0, (float) $r->live_attributed_gmv_myr),
                    ]);
                }
            });
        } catch (QueryException $e) {
            // Only treat unique-constraint violations as the "already linked" case.
            // Other DB errors (deadlock, connection drop, etc.) should bubble up.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return back()
                ->withErrors([
                    'actual_live_record_id' => 'A record was just linked elsewhere — refresh and retry.',
                ])
                ->setStatusCode(409);
        }

        $count = $records->count();
        $noun = $count === 1 ? 'record' : "{$count} records";

        return back()->with('success', "Session verified with linked TikTok {$noun}.");
    }

    public function storeAttachment(StoreLiveSessionAttachmentRequest $request, LiveSession $session): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $file = $request->file('file');
        $path = $file->store("live-sessions/{$session->id}/attachments", 'public');

        LiveSessionAttachment::create([
            'live_session_id' => $session->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'description' => $request->string('description')->toString() ?: null,
            'uploaded_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }

    public function destroyAttachment(Request $request, LiveSession $session, LiveSessionAttachment $attachment): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin_livehost', 'admin'], true), 403);
        abort_unless($attachment->live_session_id === $session->id, 404);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    public function show(Request $request, LiveSession $session): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $session->load([
            'platformAccount:id,name,platform_id',
            'platformAccount.platform:id,name,display_name,slug',
            'liveHost:id,name,email',
            'liveHostPlatformAccount.platformAccount.platform:id,name,display_name,slug',
            'verifiedBy:id,name',
            'matchedActualLiveRecord:id,launched_time,ended_time,duration_seconds,gmv_myr,live_attributed_gmv_myr,viewers,items_sold,creator_handle,source,import_id',
            'actualLiveRecords',
            'analytics',
            'attachments.uploader:id,name',
            'gmvAdjustments.adjustedBy:id,name',
        ]);

        $candidates = $this->buildCandidatePayload($session);

        return Inertia::render('sessions/Show', [
            'session' => $this->mapSession($session, detailed: true),
            'analytics' => $this->mapAnalytics($session->analytics),
            'attachments' => $session->attachments
                ->map(fn (LiveSessionAttachment $a) => $this->mapAttachment($a))
                ->values(),
            'candidates' => $candidates,
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     sessionId: string,
     *     title: ?string,
     *     description: ?string,
     *     status: string,
     *     statusColor: string,
     *     hostId: ?int,
     *     hostName: ?string,
     *     hostEmail: ?string,
     *     platformAccountId: ?int,
     *     platformAccount: ?string,
     *     platformType: ?string,
     *     platformName: ?string,
     *     scheduledStart: ?string,
     *     actualStart: ?string,
     *     actualEnd: ?string,
     *     duration: ?int,
     *     viewers: int,
     *     createdAt?: ?string,
     *     updatedAt?: ?string
     * }
     */
    private function mapSession(LiveSession $s, bool $detailed = false): array
    {
        $attachments = $s->relationLoaded('attachments')
            ? $s->attachments->map(fn (LiveSessionAttachment $a) => $this->mapAttachment($a))->values()->all()
            : [];

        $analytics = $s->relationLoaded('analytics') ? $s->analytics : null;

        $base = [
            'id' => $s->id,
            'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
            'title' => $s->title,
            'description' => $s->description,
            'remarks' => $s->remarks,
            'status' => $s->status,
            'statusColor' => $s->status_color,
            'hostId' => $s->live_host_id,
            'hostName' => $s->liveHost?->name,
            'hostEmail' => $s->liveHost?->email,
            'platformAccountId' => $s->platform_account_id,
            'platformAccount' => $s->platformAccount?->name,
            'platformType' => $s->platformAccount?->platform?->slug,
            'platformName' => $s->platformAccount?->platform?->display_name
                ?? $s->platformAccount?->platform?->name,
            'scheduledStart' => $s->scheduled_start_at?->toIso8601String(),
            'actualStart' => $s->actual_start_at?->toIso8601String(),
            'actualEnd' => $s->actual_end_at?->toIso8601String(),
            'duration' => $s->duration,
            'durationMinutes' => $s->duration_minutes,
            'missedReasonCode' => $s->missed_reason_code,
            'missedReasonNote' => $s->missed_reason_note,
            'analytics' => $this->mapAnalytics($analytics),
            'viewers' => 0,
            'attachments' => $attachments,
            'attachmentCount' => count($attachments),
            'verificationStatus' => $s->verification_status ?? 'pending',
            'verificationNotes' => $s->verification_notes,
            'verifiedById' => $s->verified_by,
            'verifiedByName' => $s->verifiedBy?->name,
            'verifiedAt' => $s->verified_at?->toIso8601String(),
        ];

        if ($detailed) {
            $base['createdAt'] = $s->created_at?->toIso8601String();
            $base['updatedAt'] = $s->updated_at?->toIso8601String();

            // Commission panel props (PIC-only surface). The /livehost/* route
            // group is already gated to admin_livehost+admin, so live hosts
            // never reach this path — no extra guard needed here.
            $adjustments = $s->relationLoaded('gmvAdjustments')
                ? $s->gmvAdjustments
                : $s->gmvAdjustments()->with('adjustedBy:id,name')->get();

            $base['gmv_amount'] = $s->gmv_amount !== null ? (float) $s->gmv_amount : 0.0;
            $base['gmv_adjustment'] = $s->gmv_adjustment !== null ? (float) $s->gmv_adjustment : 0.0;
            $base['net_gmv'] = round(
                ((float) ($s->gmv_amount ?? 0)) + ((float) ($s->gmv_adjustment ?? 0)),
                2
            );
            $base['gmv_locked_at'] = $s->gmv_locked_at?->toIso8601String();
            $base['commission_snapshot_json'] = $s->commission_snapshot_json;
            $base['gmv_adjustments'] = $adjustments
                ->map(fn (LiveSessionGmvAdjustment $a) => [
                    'id' => $a->id,
                    'amount_myr' => (float) $a->amount_myr,
                    'reason' => $a->reason,
                    'adjusted_at' => $a->adjusted_at?->toIso8601String(),
                    'adjusted_by' => $a->adjusted_by,
                    'adjusted_by_name' => $a->adjustedBy?->name,
                ])
                ->values()
                ->all();
            $base['creator_handle'] = $s->liveHostPlatformAccount?->creator_handle;
            $base['creator_platform_user_id'] = $s->liveHostPlatformAccount?->creator_platform_user_id;
            $base['payroll_locked'] = $this->isSessionPayrollLocked($s);
            $base['gmv_source'] = $s->gmv_source;
            $base['matched_actual_live_record_id'] = $s->matched_actual_live_record_id;
            $base['matched_actual_live_record'] = $s->matchedActualLiveRecord ? [
                'id' => $s->matchedActualLiveRecord->id,
                'launched_time' => $s->matchedActualLiveRecord->launched_time?->toIso8601String(),
                'ended_time' => $s->matchedActualLiveRecord->ended_time?->toIso8601String(),
                'duration_seconds' => $s->matchedActualLiveRecord->duration_seconds,
                'gmv_myr' => (float) $s->matchedActualLiveRecord->gmv_myr,
                'live_attributed_gmv_myr' => (float) $s->matchedActualLiveRecord->live_attributed_gmv_myr,
                'viewers' => $s->matchedActualLiveRecord->viewers,
                'items_sold' => $s->matchedActualLiveRecord->items_sold,
                'creator_handle' => $s->matchedActualLiveRecord->creator_handle,
                'source' => $s->matchedActualLiveRecord->source,
                'import_id' => $s->matchedActualLiveRecord->import_id,
            ] : null;

            // Full set of records attributed to this session (split-live). The
            // primary is flagged so the UI can mark it; gmv_amount is their sum.
            $base['actual_live_records'] = $s->relationLoaded('actualLiveRecords')
                ? $s->actualLiveRecords->map(fn (ActualLiveRecord $r) => [
                    'id' => $r->id,
                    'launched_time' => $r->launched_time?->toIso8601String(),
                    'ended_time' => $r->ended_time?->toIso8601String(),
                    'duration_seconds' => $r->duration_seconds,
                    'gmv_myr' => (float) $r->gmv_myr,
                    'live_attributed_gmv_myr' => (float) $r->live_attributed_gmv_myr,
                    'viewers' => $r->viewers,
                    'items_sold' => $r->items_sold,
                    'creator_handle' => $r->creator_handle,
                    'source' => $r->source,
                    'is_primary' => (bool) $r->pivot->is_primary,
                ])->sortBy('launched_time')->values()->all()
                : [];
        }

        return $base;
    }

    /**
     * Mirror of LiveSessionGmvAdjustmentController::assertNotPayrollLocked()
     * expressed as a boolean for the UI. True means the PIC Commission panel
     * must gray out Add/Delete adjustment actions.
     */
    private function isSessionPayrollLocked(LiveSession $session): bool
    {
        if ($session->actual_end_at === null) {
            return false;
        }

        return LiveHostPayrollRun::query()
            ->where('status', 'locked')
            ->where('period_start', '<=', $session->actual_end_at)
            ->where('period_end', '>=', $session->actual_end_at)
            ->exists();
    }

    /**
     * @return array{
     *     viewersPeak: int,
     *     viewersAvg: int,
     *     totalLikes: int,
     *     totalComments: int,
     *     totalShares: int,
     *     totalEngagement: int,
     *     engagementRate: float,
     *     giftsValue: string,
     *     durationMinutes: int
     * }|null
     */
    private function mapAnalytics(?LiveAnalytics $analytics): ?array
    {
        if (! $analytics) {
            return null;
        }

        return [
            'viewersPeak' => (int) $analytics->viewers_peak,
            'viewersAvg' => (int) $analytics->viewers_avg,
            'totalLikes' => (int) $analytics->total_likes,
            'totalComments' => (int) $analytics->total_comments,
            'totalShares' => (int) $analytics->total_shares,
            'totalEngagement' => $analytics->total_engagement,
            'engagementRate' => $analytics->engagement_rate,
            'giftsValue' => (string) $analytics->gifts_value,
            'durationMinutes' => (int) $analytics->duration_minutes,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     fileName: string,
     *     fileType: string,
     *     fileSize: int,
     *     fileSizeFormatted: string,
     *     fileUrl: string,
     *     description: ?string,
     *     uploaderName: ?string,
     *     isImage: bool,
     *     isVideo: bool,
     *     isPdf: bool,
     *     createdAt: ?string
     * }
     */
    private function mapAttachment(LiveSessionAttachment $a): array
    {
        return [
            'id' => $a->id,
            'fileName' => $a->file_name,
            'fileType' => $a->file_type,
            'fileSize' => (int) $a->file_size,
            'fileSizeFormatted' => $a->file_size_formatted,
            'fileUrl' => $a->file_url,
            'description' => $a->description,
            'uploaderName' => $a->uploader?->name,
            'isImage' => $a->isImage(),
            'isVideo' => $a->isVideo(),
            'isPdf' => $a->isPdf(),
            'createdAt' => $a->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, email: ?string}>
     */
    private function hostOptions(): Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }

    /**
     * @return Collection<int, array{id: int, name: string, platform: ?string}>
     */
    private function platformAccountOptions(): Collection
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
