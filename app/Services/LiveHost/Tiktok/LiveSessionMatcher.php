<?php

declare(strict_types=1);

namespace App\Services\LiveHost\Tiktok;

use App\Models\LiveSession;
use App\Models\TiktokLiveReport;
use App\Services\LiveHost\LiveAccountResolver;

class LiveSessionMatcher
{
    public function __construct(private LiveAccountResolver $accounts) {}

    /**
     * Number of minutes of leeway on either side of the report's launched_time
     * when searching for a matching LiveSession by actual_start_at.
     */
    private const WINDOW_MINUTES = 30;

    /**
     * Pair a TiktokLiveReport row with the correct LiveSession.
     *
     * The report identifies the creator ACCOUNT that went live (the punca
     * kuasa): a numeric Creator ID when present, else the @nickname (CSV rows
     * often omit the id). We resolve that to a LiveAccount and match sessions
     * by live_account_id within a +/- 30min window on actual_start_at, picking
     * the closest. Legacy sessions that predate the account model fall back to
     * the old creator-pivot match. Returns null when nothing qualifies so the
     * UI can flag the row for PIC review.
     *
     * When $platformAccountId is provided the candidate set is constrained to
     * sessions on that TikTok Shop — the same account can be live for sibling
     * shops, and the report row tells us which one this live belongs to.
     */
    public function match(TiktokLiveReport $report, ?int $platformAccountId = null): ?LiveSession
    {
        $launchedAt = $report->launched_time;

        if ($launchedAt === null) {
            return null;
        }

        $account = $this->accounts->fromCreatorId($report->tiktok_creator_id)
            ?? $this->accounts->fromHandle($report->creator_nickname);

        $base = LiveSession::query()
            ->when($platformAccountId !== null, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->whereNotNull('actual_start_at')
            ->whereBetween('actual_start_at', [
                $launchedAt->copy()->subMinutes(self::WINDOW_MINUTES),
                $launchedAt->copy()->addMinutes(self::WINDOW_MINUTES),
            ]);

        $candidates = collect();

        if ($account !== null) {
            $candidates = (clone $base)->where('live_account_id', $account->id)->get();
        }

        // Legacy fallback: sessions with no live_account_id resolved yet, keyed
        // on the old creator-identity pivot by numeric Creator ID.
        if ($candidates->isEmpty()) {
            $creatorId = $report->tiktok_creator_id;
            if ($creatorId !== null && $creatorId !== '') {
                $candidates = (clone $base)
                    ->whereHas('liveHostPlatformAccount', fn ($q) => $q->where('creator_platform_user_id', $creatorId))
                    ->get();
            }
        }

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
