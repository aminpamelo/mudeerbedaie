<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\SessionReplacementRequest;
use Illuminate\Http\Request;
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
}
