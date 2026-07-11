<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreSessionSlotRequest;
use App\Http\Requests\LiveHost\UpdateSessionSlotRequest;
use App\Models\LiveAccount;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Notifications\LiveHost\ScheduleSlotChangedNotification;
use App\Services\LiveHost\SuggestedSlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SessionSlotController extends Controller
{
    public function index(Request $request): Response
    {
        $host = $request->string('host')->toString();
        $platformAccount = $request->string('platform_account')->toString();
        $liveAccount = $request->string('live_account')->toString();
        $status = $request->string('status')->toString();
        $dayOfWeek = $request->string('day_of_week')->toString();
        $mode = $request->string('mode')->toString();
        $scheduleDate = $request->string('schedule_date')->toString();

        $assignments = LiveScheduleAssignment::query()
            ->with([
                'liveHost:id,name,email',
                'liveAccount:id,nickname,display_name,creator_user_id,needs_review',
                'platformAccount:id,name,platform_id',
                'platformAccount.platform:id,name,display_name,slug',
                'timeSlot:id,start_time,end_time,day_of_week,platform_account_id',
                'createdBy:id,name',
                'liveSession.liveHost:id,name,email',
                'liveSession.platformAccount:id,name,platform_id',
                'liveSession.platformAccount.platform:id,name,display_name,slug',
                'liveSession.attachments.uploader:id,name',
                'liveSession.verifiedBy:id,name',
                'liveSession.analytics',
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
            ->when(
                $liveAccount !== '',
                fn ($q) => $q->where('live_account_id', $liveAccount)
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
            ->when($scheduleDate !== '', fn ($q) => $q->whereDate('schedule_date', $scheduleDate))
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
                'live_account' => $liveAccount,
                'status' => $status,
                'day_of_week' => $dayOfWeek,
                'mode' => $mode,
                'schedule_date' => $scheduleDate,
            ],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
            'liveAccounts' => $this->liveAccountOptions(),
            'timeSlots' => $this->timeSlotOptions(),
            'hostPlatformPivots' => $this->hostPlatformPivotOptions(),
        ]);
    }

    public function calendar(Request $request): Response
    {
        $host = $request->string('host')->toString();
        $platformAccount = $request->string('platform_account')->toString();
        $liveAccount = $request->string('live_account')->toString();
        $status = $request->string('status')->toString();
        $mode = $request->string('mode')->toString();
        $weekOf = $request->string('week_of')->toString();
        $showSuggestions = $request->string('show_suggestions')->toString();
        $includeUnlinked = $request->boolean('include_unlinked');

        $weekStart = $weekOf !== ''
            ? CarbonImmutable::parse($weekOf)->startOfWeek(CarbonImmutable::SUNDAY)
            : CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);
        $weekEnd = $weekStart->endOfWeek(CarbonImmutable::SATURDAY);

        $assignments = LiveScheduleAssignment::query()
            ->with([
                'liveHost:id,name,email',
                'liveAccount:id,nickname,display_name,creator_user_id,needs_review',
                'platformAccount:id,name,platform_id',
                'platformAccount.platform:id,name,display_name,slug',
                'timeSlot:id,start_time,end_time,day_of_week,platform_account_id',
                'createdBy:id,name',
                'liveSession.liveHost:id,name,email',
                'liveSession.platformAccount:id,name,platform_id',
                'liveSession.platformAccount.platform:id,name,display_name,slug',
                'liveSession.attachments.uploader:id,name',
                'liveSession.verifiedBy:id,name',
                'liveSession.analytics',
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
            ->when(
                $liveAccount !== '',
                fn ($q) => $q->where('live_account_id', $liveAccount)
            )
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when(
                $mode === 'template',
                fn ($q) => $q->where('is_template', true),
                fn ($q) => $q->when(
                    $mode === 'dated',
                    fn ($q) => $q->where('is_template', false),
                    fn ($q) => $q->where(function ($q) use ($weekStart, $weekEnd) {
                        $q->where('is_template', true)
                            ->orWhereNull('schedule_date')
                            ->orWhere(function ($q) use ($weekStart, $weekEnd) {
                                $q->whereDate('schedule_date', '>=', $weekStart->toDateString())
                                    ->whereDate('schedule_date', '<=', $weekEnd->toDateString());
                            });
                    })
                )
            )
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (LiveScheduleAssignment $a) => $this->mapAssignment($a));

        $timeSlots = $this->timeSlotOptions();

        // TikTok lives with no recorded session yet, surfaced as "assign from
        // TikTok" ghosts. Suppressed in the templates-only view (suggestions are
        // inherently dated) and when the PIC toggles them off.
        $suggestions = ($showSuggestions !== '0' && $mode !== 'template')
            ? app(SuggestedSlotFinder::class)->forWeek(
                $weekStart,
                $weekEnd,
                $platformAccount !== '' ? (int) $platformAccount : null,
                $liveAccount !== '' ? (int) $liveAccount : null,
                $timeSlots->all(),
                $includeUnlinked
            )
            : [];

        return Inertia::render('session-slots/Calendar', [
            'sessionSlots' => $assignments,
            'suggestions' => $suggestions,
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEnd->toDateString(),
            'filters' => [
                'host' => $host,
                'platform_account' => $platformAccount,
                'live_account' => $liveAccount,
                'status' => $status,
                'mode' => $mode,
                'week_of' => $weekStart->toDateString(),
                'show_suggestions' => $showSuggestions === '0' ? '0' : '1',
                'include_unlinked' => $includeUnlinked ? '1' : '0',
            ],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
            'liveAccounts' => $this->liveAccountOptions(),
            'timeSlots' => $timeSlots,
            'hostPlatformPivots' => $this->hostPlatformPivotOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('session-slots/Create', [
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
            'liveAccounts' => $this->liveAccountOptions(),
            'timeSlots' => $this->timeSlotOptions(),
            'hostPlatformPivots' => $this->hostPlatformPivotOptions(),
        ]);
    }

    public function store(StoreSessionSlotRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;
        $data['status'] = $data['status'] ?? 'scheduled';

        $sessionSlot = LiveScheduleAssignment::create($data);

        if ($sessionSlot->live_host_id !== null && $this->isFutureDated($sessionSlot)) {
            User::find($sessionSlot->live_host_id)
                ?->notify(new ScheduleSlotChangedNotification($sessionSlot, 'assigned'));
        }

        $redirect = match ($request->string('return_to')->toString()) {
            'calendar' => redirect()->route('livehost.session-slots.calendar', $request->only(['week_of'])),
            'table' => redirect()->route('livehost.session-slots.table'),
            default => redirect()->route('livehost.session-slots.index'),
        };

        return $redirect->with('success', 'Session slot created.');
    }

    public function show(LiveScheduleAssignment $sessionSlot): Response
    {
        $sessionSlot->load([
            'liveHost:id,name,email',
            'liveAccount:id,nickname,display_name,creator_user_id,needs_review',
            'platformAccount:id,name,platform_id',
            'platformAccount.platform:id,name,display_name,slug',
            'timeSlot:id,start_time,end_time,day_of_week,platform_account_id',
            'createdBy:id,name',
            'liveSession.liveHost:id,name,email',
            'liveSession.platformAccount:id,name,platform_id',
            'liveSession.platformAccount.platform:id,name,display_name,slug',
            'liveSession.attachments.uploader:id,name',
            'liveSession.verifiedBy:id,name',
            'liveSession.analytics',
        ]);

        return Inertia::render('session-slots/Show', [
            'sessionSlot' => $this->mapAssignment($sessionSlot),
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
            'liveAccounts' => $this->liveAccountOptions(),
            'timeSlots' => $this->timeSlotOptions(),
            'hostPlatformPivots' => $this->hostPlatformPivotOptions(),
        ]);
    }

    public function edit(LiveScheduleAssignment $sessionSlot): Response
    {
        return Inertia::render('session-slots/Edit', [
            'sessionSlot' => [
                'id' => $sessionSlot->id,
                'platform_account_id' => $sessionSlot->platform_account_id,
                'live_host_platform_account_id' => $sessionSlot->live_host_platform_account_id,
                'live_account_id' => $sessionSlot->live_account_id,
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
            'liveAccounts' => $this->liveAccountOptions(),
            'timeSlots' => $this->timeSlotOptions(),
            'hostPlatformPivots' => $this->hostPlatformPivotOptions(),
        ]);
    }

    public function update(UpdateSessionSlotRequest $request, LiveScheduleAssignment $sessionSlot): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? $sessionSlot->status;

        $sessionSlot->update($data);

        $this->pushSlotUpdate($sessionSlot);

        $redirect = match ($request->string('return_to')->toString()) {
            'calendar' => redirect()->route('livehost.session-slots.calendar', $request->only(['week_of'])),
            'table' => redirect()->route('livehost.session-slots.table'),
            'show' => redirect()->route('livehost.session-slots.show', $sessionSlot),
            default => redirect()->route('livehost.session-slots.show', $sessionSlot),
        };

        return $redirect->with('success', 'Session slot updated.');
    }

    /**
     * True for dated (non-template), today-or-future slots — the only ones a
     * host can still act on, so the only ones worth a push.
     */
    private function isFutureDated(LiveScheduleAssignment $slot): bool
    {
        return ! $slot->is_template
            && $slot->schedule_date !== null
            && CarbonImmutable::parse($slot->schedule_date)->gte(CarbonImmutable::today());
    }

    /**
     * Notify the host when their dated slot meaningfully changes. A newly
     * assigned host is told "assigned"; an existing host whose time / creator
     * account / platform / status moved is told "updated". Pure reassignments
     * that go through the replacement workflow update the row outside this
     * controller, so they never reach here — no double notification.
     */
    private function pushSlotUpdate(LiveScheduleAssignment $slot): void
    {
        if (! $this->isFutureDated($slot) || $slot->live_host_id === null) {
            return;
        }

        if ($slot->wasChanged('live_host_id')) {
            User::find($slot->live_host_id)
                ?->notify(new ScheduleSlotChangedNotification($slot, 'assigned'));

            return;
        }

        $detailsChanged = $slot->wasChanged([
            'time_slot_id', 'schedule_date', 'platform_account_id', 'live_account_id', 'status',
        ]);

        if ($detailsChanged) {
            User::find($slot->live_host_id)
                ?->notify(new ScheduleSlotChangedNotification($slot, 'updated'));
        }
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

        $account = $a->liveAccount;
        $accountLabel = $account
            ? ($account->nickname ?: $account->display_name ?: ($account->creator_user_id ? "Creator {$account->creator_user_id}" : null))
            : null;

        return [
            'id' => $a->id,
            // Punca kuasa: the creator account the host goes live on.
            'liveAccountId' => $a->live_account_id,
            'liveAccountLabel' => $accountLabel,
            'liveAccountDisplayName' => $account?->display_name,
            'creatorUserId' => $account?->creator_user_id,
            'liveAccountNeedsReview' => (bool) ($account?->needs_review),
            // Commerce reference (the shop being promoted in this block).
            'platformAccountId' => $a->platform_account_id,
            'platformAccount' => $a->platformAccount?->name,
            'platformType' => $a->platformAccount?->platform?->slug,
            'liveHostPlatformAccountId' => $a->live_host_platform_account_id,
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
            // The actual broadcast tied to this slot — carries lifecycle status,
            // GMV, the host's proof upload and the PIC verification state so the
            // calendar can flag "still needs upload" / "still needs verify".
            'session' => $a->relationLoaded('liveSession') && $a->liveSession
                ? $this->mapLinkedSession($a->liveSession)
                : null,
        ];
    }

    /**
     * The linked live session in the exact shape LiveSessionModal consumes
     * (so the calendar can reuse the full upload + verify surface), plus the
     * derived indicators the calendar block renders at a glance.
     *
     * @return array<string, mixed>
     */
    private function mapLinkedSession(LiveSession $s): array
    {
        $attachments = $s->relationLoaded('attachments')
            ? $s->attachments->map(fn (LiveSessionAttachment $a) => [
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
                'attachmentType' => $a->attachment_type,
                'createdAt' => $a->created_at?->toIso8601String(),
            ])->values()->all()
            : [];

        $analytics = $s->relationLoaded('analytics') && $s->analytics ? [
            'viewersPeak' => (int) $s->analytics->viewers_peak,
            'viewersAvg' => (int) $s->analytics->viewers_avg,
            'totalLikes' => (int) $s->analytics->total_likes,
            'totalComments' => (int) $s->analytics->total_comments,
            'totalShares' => (int) $s->analytics->total_shares,
            'totalEngagement' => $s->analytics->total_engagement,
            'engagementRate' => $s->analytics->engagement_rate,
            'giftsValue' => (string) $s->analytics->gifts_value,
            'durationMinutes' => (int) $s->analytics->duration_minutes,
        ] : null;

        $uploaded = $s->uploaded_at !== null;
        $hasScreenshot = collect($attachments)
            ->contains(fn (array $a): bool => $a['attachmentType'] === LiveSessionAttachment::TYPE_TIKTOK_SHOP_SCREENSHOT);

        // "Needs upload" is the host's outstanding obligation: a session with no
        // recap upload that is either already ended OR is past its scheduled
        // start (the window to go live has closed). Cancelled / missed sessions
        // are excluded — they're already accounted for. "Overdue" is the loudest
        // case: the slot's time passed and it was never even ended.
        $isActionable = ! in_array($s->status, ['cancelled', 'missed'], true);
        $isPast = $s->scheduled_start_at !== null && $s->scheduled_start_at->isPast();
        $needsUpload = $isActionable && ! $uploaded && ($s->status === 'ended' || $isPast);
        $overdue = $isActionable && ! $uploaded && $s->status !== 'ended' && $isPast;

        return [
            'id' => $s->id,
            'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
            'title' => $s->title,
            'description' => $s->description,
            'status' => $s->status,
            'statusColor' => $s->status_color,
            'hostId' => $s->live_host_id,
            'hostName' => $s->liveHost?->name,
            'hostEmail' => $s->liveHost?->email,
            'platformAccountId' => $s->platform_account_id,
            'platformAccount' => $s->platformAccount?->name,
            'platformType' => $s->platformAccount?->platform?->slug,
            'scheduledStart' => $s->scheduled_start_at?->toIso8601String(),
            'actualStart' => $s->actual_start_at?->toIso8601String(),
            'actualEnd' => $s->actual_end_at?->toIso8601String(),
            'duration' => $s->duration,
            'durationMinutes' => $s->duration_minutes,
            'remarks' => $s->remarks,
            'missedReasonCode' => $s->missed_reason_code,
            'missedReasonNote' => $s->missed_reason_note,
            'analytics' => $analytics,
            'attachments' => $attachments,
            'attachmentCount' => count($attachments),
            'verificationStatus' => $s->verification_status ?? 'pending',
            'verificationNotes' => $s->verification_notes,
            'verifiedByName' => $s->verifiedBy?->name,
            'verifiedAt' => $s->verified_at?->toIso8601String(),
            // Derived calendar indicators.
            'gmvNet' => round(((float) ($s->gmv_amount ?? 0)) + ((float) ($s->gmv_adjustment ?? 0)), 2),
            'gmvSource' => $s->gmv_source,
            'uploaded' => $uploaded,
            'uploadedAt' => $s->uploaded_at?->toIso8601String(),
            'hasScreenshot' => $hasScreenshot,
            'needsUpload' => $needsUpload,
            'overdue' => $overdue,
        ];
    }

    /**
     * Pivot options for the creator-identity picker in the session-slot
     * modal. One entry per (host, platform_account) pairing, with the handle
     * and a precomputed label "(shop - @creator)" so the React UI can render
     * the option text without additional lookups. `isPrimary` lets the UI
     * auto-select the host's default identity for a given platform account.
     *
     * @return Collection<int, array{
     *     id: int,
     *     userId: int,
     *     userName: ?string,
     *     platformAccountId: int,
     *     creatorHandle: ?string,
     *     creatorPlatformUserId: ?string,
     *     isPrimary: bool,
     *     label: string
     * }>
     */
    private function hostPlatformPivotOptions(): Collection
    {
        return LiveHostPlatformAccount::query()
            ->with(['platformAccount:id,name', 'user:id,name'])
            ->orderByDesc('is_primary')
            ->get()
            ->map(function (LiveHostPlatformAccount $pivot) {
                $shopName = $pivot->platformAccount?->name ?? 'Unknown shop';
                $handle = $pivot->creator_handle !== null && $pivot->creator_handle !== ''
                    ? $pivot->creator_handle
                    : '—';

                return [
                    'id' => $pivot->id,
                    'userId' => $pivot->user_id,
                    'userName' => $pivot->user?->name,
                    'platformAccountId' => $pivot->platform_account_id,
                    'creatorHandle' => $pivot->creator_handle,
                    'creatorPlatformUserId' => $pivot->creator_platform_user_id,
                    'isPrimary' => (bool) $pivot->is_primary,
                    'label' => "{$shopName} - {$handle}",
                ];
            });
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

    /**
     * Creator accounts (the scheduling punca kuasa) for the nickname-first
     * picker and the calendar's account filter/lanes. Each entry carries the
     * shops the account may promote (so the modal can derive/limit the shop)
     * and the eligible operating hosts.
     *
     * @return Collection<int, array{
     *     id: int,
     *     label: string,
     *     nickname: ?string,
     *     displayName: ?string,
     *     creatorUserId: ?string,
     *     needsReview: bool,
     *     shops: array<int, array{id: int, name: ?string, isPrimary: bool}>,
     *     hostIds: array<int, int>
     * }>
     */
    private function liveAccountOptions(): Collection
    {
        return LiveAccount::query()
            ->where('is_active', true)
            ->with(['shops:id,name', 'hosts:id'])
            ->orderByRaw('COALESCE(nickname, display_name)')
            ->get()
            ->map(function (LiveAccount $account) {
                return [
                    'id' => $account->id,
                    'label' => $account->label,
                    'nickname' => $account->nickname,
                    'displayName' => $account->display_name,
                    'creatorUserId' => $account->creator_user_id,
                    'needsReview' => (bool) $account->needs_review,
                    'shops' => $account->shops->map(fn (PlatformAccount $shop) => [
                        'id' => $shop->id,
                        'name' => $shop->name,
                        'isPrimary' => (bool) $shop->pivot->is_primary,
                    ])->values()->all(),
                    'hostIds' => $account->hosts->pluck('id')->all(),
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     label: string,
     *     dayOfWeek: ?int,
     *     platformAccountId: ?int,
     *     startTime: string,
     *     endTime: string
     * }>
     */
    private function timeSlotOptions(): Collection
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
