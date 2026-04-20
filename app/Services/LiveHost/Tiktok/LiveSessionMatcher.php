<?php

declare(strict_types=1);

namespace App\Services\LiveHost\Tiktok;

use App\Models\LiveSession;
use App\Models\TiktokLiveReport;

class LiveSessionMatcher
{
    /**
     * Number of minutes of leeway on either side of the report's launched_time
     * when searching for a matching LiveSession by actual_start_at.
     */
    private const WINDOW_MINUTES = 30;

    /**
     * Pair a TiktokLiveReport row with the correct LiveSession using
     * creator id (from the live_host_platform_account pivot) and a +/- 30min
     * window on actual_start_at. When multiple candidates are eligible, the
     * session whose actual_start_at is closest to the report's launched_time
     * wins. Returns null when nothing qualifies so the UI can flag the row
     * for PIC review.
     *
     * When $platformAccountId is provided the candidate set is further
     * constrained to sessions attached to that specific TikTok Shop — this
     * stops a single creator id from pulling in sessions on sibling shops
     * that happen to share the same creator pivot.
     */
    public function match(TiktokLiveReport $report, ?int $platformAccountId = null): ?LiveSession
    {
        $launchedAt = $report->launched_time;
        $creatorId = $report->tiktok_creator_id;

        if ($launchedAt === null || $creatorId === null || $creatorId === '') {
            return null;
        }

        $candidates = LiveSession::query()
            ->whereHas('liveHostPlatformAccount', function ($query) use ($creatorId) {
                $query->where('creator_platform_user_id', $creatorId);
            })
            ->when($platformAccountId !== null, function ($query) use ($platformAccountId) {
                $query->where('platform_account_id', $platformAccountId);
            })
            ->whereNotNull('actual_start_at')
            ->whereBetween('actual_start_at', [
                $launchedAt->copy()->subMinutes(self::WINDOW_MINUTES),
                $launchedAt->copy()->addMinutes(self::WINDOW_MINUTES),
            ])
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(fn (LiveSession $session) => abs(
                $launchedAt->diffInSeconds($session->actual_start_at, true)
            ))
            ->first();
    }
}
