<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignReplacementRequest;
use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Notifications\ReplacementAssignedToYouNotification;
use App\Notifications\ReplacementResolvedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReplacementRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', SessionReplacementRequest::STATUS_PENDING);

        $requests = SessionReplacementRequest::query()
            ->with([
                'assignment.timeSlot',
                'assignment.platformAccount.platform',
                'originalHost:id,name,email',
                'replacementHost:id,name,email',
            ])
            ->where('status', $status)
            ->orderBy('expires_at')
            ->get()
            ->map(fn (SessionReplacementRequest $req) => [
                'id' => $req->id,
                'scope' => $req->scope,
                'targetDate' => $req->target_date?->toDateString(),
                'reasonCategory' => $req->reason_category,
                'reasonNote' => $req->reason_note,
                'status' => $req->status,
                'requestedAt' => $req->requested_at?->toIso8601String(),
                'expiresAt' => $req->expires_at?->toIso8601String(),
                'originalHost' => [
                    'id' => $req->originalHost?->id,
                    'name' => $req->originalHost?->name,
                ],
                'replacementHost' => $req->replacementHost ? [
                    'id' => $req->replacementHost->id,
                    'name' => $req->replacementHost->name,
                ] : null,
                'slot' => [
                    'dayOfWeek' => $req->assignment?->day_of_week,
                    'startTime' => $req->assignment?->timeSlot?->start_time,
                    'endTime' => $req->assignment?->timeSlot?->end_time,
                    'platformAccount' => $req->assignment?->platformAccount?->name,
                ],
            ]);

        $counts = SessionReplacementRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Replacements/Index', [
            'requests' => $requests,
            'currentStatus' => $status,
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'assigned' => (int) ($counts['assigned'] ?? 0),
                'expired' => (int) ($counts['expired'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'withdrawn' => (int) ($counts['withdrawn'] ?? 0),
            ],
        ]);
    }

    public function show(SessionReplacementRequest $replacementRequest): Response
    {
        $replacementRequest->load([
            'assignment.timeSlot',
            'assignment.platformAccount.platform',
            'originalHost:id,name,email',
            'replacementHost:id,name,email',
            'assignedBy:id,name',
        ]);

        $assignment = $replacementRequest->assignment;
        $availableHosts = $this->resolveAvailableHosts($replacementRequest);

        $repeatStat = SessionReplacementRequest::query()
            ->where('original_host_id', $replacementRequest->original_host_id)
            ->whereIn('status', [
                SessionReplacementRequest::STATUS_ASSIGNED,
                SessionReplacementRequest::STATUS_EXPIRED,
                SessionReplacementRequest::STATUS_WITHDRAWN,
            ])
            ->where('requested_at', '>=', now()->subDays(90))
            ->count();

        return Inertia::render('Replacements/Show', [
            'request' => [
                'id' => $replacementRequest->id,
                'scope' => $replacementRequest->scope,
                'status' => $replacementRequest->status,
                'targetDate' => $replacementRequest->target_date?->toDateString(),
                'reasonCategory' => $replacementRequest->reason_category,
                'reasonNote' => $replacementRequest->reason_note,
                'requestedAt' => $replacementRequest->requested_at?->toIso8601String(),
                'expiresAt' => $replacementRequest->expires_at?->toIso8601String(),
                'rejectionReason' => $replacementRequest->rejection_reason,
                'originalHost' => [
                    'id' => $replacementRequest->originalHost?->id,
                    'name' => $replacementRequest->originalHost?->name,
                    'priorRequests90d' => $repeatStat,
                ],
                'replacementHost' => $replacementRequest->replacementHost ? [
                    'id' => $replacementRequest->replacementHost->id,
                    'name' => $replacementRequest->replacementHost->name,
                ] : null,
                'slot' => [
                    'dayOfWeek' => $assignment?->day_of_week,
                    'startTime' => $assignment?->timeSlot?->start_time,
                    'endTime' => $assignment?->timeSlot?->end_time,
                    'platformAccount' => $assignment?->platformAccount?->name,
                ],
            ],
            'availableHosts' => $availableHosts,
        ]);
    }

    public function assign(AssignReplacementRequest $request, SessionReplacementRequest $replacementRequest): RedirectResponse
    {
        abort_unless(
            $replacementRequest->isPending(),
            422,
            'Permohonan ini tidak lagi tertunda.'
        );

        DB::transaction(function () use ($request, $replacementRequest) {
            $replacementRequest->update([
                'status' => SessionReplacementRequest::STATUS_ASSIGNED,
                'replacement_host_id' => $request->validated('replacement_host_id'),
                'assigned_at' => now(),
                'assigned_by_id' => $request->user()->id,
            ]);

            if ($replacementRequest->scope === SessionReplacementRequest::SCOPE_PERMANENT) {
                $replacementRequest->assignment()->update([
                    'live_host_id' => $request->validated('replacement_host_id'),
                ]);
            }
        });

        $replacementRequest->refresh()->loadMissing(['replacementHost', 'originalHost']);
        $replacementRequest->replacementHost?->notify(
            new ReplacementAssignedToYouNotification($replacementRequest)
        );
        $replacementRequest->originalHost?->notify(
            new ReplacementResolvedNotification($replacementRequest, ReplacementResolvedNotification::RESOLUTION_ASSIGNED)
        );

        return redirect()
            ->route('livehost.replacements.show', $replacementRequest)
            ->with('success', 'Pengganti telah ditetapkan.');
    }

    public function reject(Request $request, SessionReplacementRequest $replacementRequest): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'admin_livehost'], true), 403);
        abort_unless($replacementRequest->isPending(), 422, 'Permohonan ini tidak lagi tertunda.');

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'rejection_reason.required' => 'Sila berikan sebab penolakan.',
            'rejection_reason.max' => 'Sebab tidak boleh melebihi 500 aksara.',
        ]);

        $replacementRequest->update([
            'status' => SessionReplacementRequest::STATUS_REJECTED,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        $replacementRequest->loadMissing('originalHost');
        $replacementRequest->originalHost?->notify(
            new ReplacementResolvedNotification($replacementRequest, ReplacementResolvedNotification::RESOLUTION_REJECTED)
        );

        return redirect()
            ->route('livehost.replacements.show', $replacementRequest)
            ->with('success', 'Permohonan telah ditolak.');
    }

    private function resolveAvailableHosts(SessionReplacementRequest $req): array
    {
        $assignment = $req->assignment;
        if (! $assignment) {
            return [];
        }

        $busyHostIds = LiveScheduleAssignment::query()
            ->where('day_of_week', $assignment->day_of_week)
            ->where('time_slot_id', $assignment->time_slot_id)
            ->where('status', '!=', 'cancelled')
            ->pluck('live_host_id')
            ->filter()
            ->all();

        return User::query()
            ->where('role', 'live_host')
            ->where('id', '!=', $req->original_host_id)
            ->whereNotIn('id', $busyHostIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'priorReplacementsCount' => SessionReplacementRequest::query()
                    ->where('replacement_host_id', $u->id)
                    ->where('status', SessionReplacementRequest::STATUS_ASSIGNED)
                    ->where('assigned_at', '>=', now()->subDays(90))
                    ->count(),
            ])
            ->all();
    }
}
