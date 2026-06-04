<?php

declare(strict_types=1);

namespace App\Services\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveSession;
use Illuminate\Support\Collection;

class ActualLiveRecordCandidateFinder
{
    private const TIMEZONE = 'Asia/Kuala_Lumpur';

    private const MAX_CANDIDATES = 20;

    /**
     * Return candidate ActualLiveRecord rows for the given LiveSession, filtered to
     * same shop + same creator account + same calendar day (KL timezone),
     * excluding records already linked to other sessions, sorted by time
     * proximity to the session's scheduled_start_at.
     *
     * Identity comes from the session's live account (the punca kuasa): its
     * numeric Creator ID and/or normalized handle. We match actual records on
     * either, because CSV imports frequently lack the numeric id and carry only
     * the handle. Legacy sessions fall back to the old creator-identity pivot.
     */
    public function forSession(LiveSession $session): Collection
    {
        $account = $session->liveAccount;
        $creatorId = $account?->creator_user_id
            ?? $session->liveHostPlatformAccount?->creator_platform_user_id;
        $normalizedHandle = $account?->normalized_handle;

        if (($creatorId === null && $normalizedHandle === null) || $session->scheduled_start_at === null) {
            return collect();
        }

        $scheduledKl = $session->scheduled_start_at->copy()->setTimezone(self::TIMEZONE);
        $dayStart = $scheduledKl->copy()->startOfDay();
        $dayEnd = $scheduledKl->copy()->endOfDay();

        return ActualLiveRecord::query()
            ->where('platform_account_id', $session->platform_account_id)
            ->where(function ($q) use ($creatorId, $normalizedHandle) {
                if ($creatorId !== null) {
                    $q->orWhere('creator_platform_user_id', $creatorId);
                }
                if ($normalizedHandle !== null) {
                    $q->orWhereRaw('LOWER(TRIM(creator_handle)) = ?', [$normalizedHandle]);
                }
            })
            ->whereBetween('launched_time', [$dayStart, $dayEnd])
            ->whereNotIn('id', function ($q) use ($session) {
                $q->select('matched_actual_live_record_id')
                    ->from('live_sessions')
                    ->whereNotNull('matched_actual_live_record_id')
                    ->where('id', '!=', $session->id);
            })
            ->get()
            ->sortBy(fn (ActualLiveRecord $r) => abs(
                $r->launched_time->diffInSeconds($session->scheduled_start_at, true)
            ))
            ->take(self::MAX_CANDIDATES)
            ->values();
    }
}
