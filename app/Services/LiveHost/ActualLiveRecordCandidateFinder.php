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
     * same platform account + same creator + same calendar day (KL timezone),
     * excluding records already linked to other sessions, sorted by time
     * proximity to the session's scheduled_start_at.
     */
    public function forSession(LiveSession $session): Collection
    {
        $pivot = $session->liveHostPlatformAccount;
        $creatorId = $pivot?->creator_platform_user_id;

        if ($creatorId === null || $session->scheduled_start_at === null) {
            return collect();
        }

        $scheduledKl = $session->scheduled_start_at->copy()->setTimezone(self::TIMEZONE);
        $dayStart = $scheduledKl->copy()->startOfDay();
        $dayEnd = $scheduledKl->copy()->endOfDay();

        return ActualLiveRecord::query()
            ->where('platform_account_id', $session->platform_account_id)
            ->where('creator_platform_user_id', $creatorId)
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
