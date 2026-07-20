<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\Mentoring\MenteeDailySalesResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Today screen.
 *
 * Renders the host-scoped Today aggregation (live-now card, today stats,
 * up-next list). All queries are scoped to `auth()->user()->id` via the
 * `live_host_id` column on `live_sessions` so one host cannot see another
 * host's data. The legacy `platform_accounts.user_id` guard (still used on
 * the Volt sessions-show page) is intentionally bypassed here — the
 * `live_host_platform_account` pivot + `live_sessions.live_host_id` are
 * authoritative for host-side queries.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $host = $request->user();

        $liveNow = LiveSession::query()
            ->with(['platformAccount.platform', 'liveAccount'])
            ->where('live_host_id', $host->id)
            ->where('status', 'live')
            ->orderByDesc('actual_start_at')
            ->get()
            ->map(fn (LiveSession $session): array => $this->liveSessionDto($session));

        $sessionsToday = LiveSession::query()
            ->where('live_host_id', $host->id)
            ->whereDate('scheduled_start_at', today())
            ->count();

        $sessionsDoneToday = LiveSession::query()
            ->where('live_host_id', $host->id)
            ->where('status', 'ended')
            ->whereDate('actual_end_at', today())
            ->count();

        $watchMinutesToday = (int) LiveSession::query()
            ->where('live_host_id', $host->id)
            ->where('status', 'ended')
            ->whereDate('actual_end_at', today())
            ->sum('duration_minutes');

        $upcoming = LiveSession::query()
            ->with(['platformAccount.platform', 'liveAccount'])
            ->where('live_host_id', $host->id)
            ->where('status', 'scheduled')
            ->where('scheduled_start_at', '>', now())
            ->orderBy('scheduled_start_at')
            ->take(2)
            ->get()
            ->map(fn (LiveSession $session): array => $this->upcomingDto($session));

        // Exclude archived programs so their mentoring glance + performance
        // summary don't show on the host's home screen.
        $mentee = $host->activeMenteeEnrollment()
            ->inLiveProgram()
            ->with(['level:id,name,color,monthly_sales_target', 'monthlyScores'])
            ->first();

        return Inertia::render('Today', [
            'liveNow' => $liveNow,
            'stats' => [
                'sessionsToday' => $sessionsToday,
                'sessionsDoneToday' => $sessionsDoneToday,
                'watchMinutesToday' => $watchMinutesToday,
            ],
            'upcoming' => $upcoming,
            'mentoring' => $this->mentoringGlance($host, $mentee),
            'videoLog' => $this->videoGlance($mentee),
            'performanceSummary' => $this->performanceSummary($mentee),
        ]);
    }

    /**
     * A compact performance snapshot for the Today home screen: the host's
     * latest overall monthly score (with its month-over-month delta and a
     * 6-month trend), this month's sales against the level target, and their
     * cohort leaderboard rank. Deliberately mirrors the figures on the
     * Performance ("My Path") tab so the home glance and the deep page never
     * disagree. Null when the host isn't in an active mentoring program.
     *
     * @return array{score: int|null, score_delta: int|null, trend: list<int|null>, sales_month: float, sales_target: int|null, sales_pct: int|null, rank: int|null, cohort_size: int}|null
     */
    private function performanceSummary(?LiveHostMentee $mentee): ?array
    {
        if ($mentee === null) {
            return null;
        }

        $now = CarbonImmutable::now();
        $target = $mentee->level?->monthly_sales_target;

        $periods = collect(range(5, 0))
            ->map(fn (int $i): CarbonImmutable => $now->subMonths($i))
            ->map(fn (CarbonImmutable $d): array => ['year' => (int) $d->format('Y'), 'month' => (int) $d->format('n')])
            ->all();

        $salesByMonth = app(MenteeDailySalesResolver::class)->monthlyTotals(collect([$mentee]), $periods);
        $scoresByKey = $mentee->monthlyScores->keyBy(fn ($s): string => sprintf('%04d-%02d', $s->year, $s->month));

        // Overall = mean of the available parts of [attitude, sales%] per month,
        // the same recipe the Performance tab uses.
        $rows = array_map(function (array $p) use ($target, $salesByMonth, $scoresByKey, $mentee): array {
            $key = sprintf('%04d-%02d', $p['year'], $p['month']);
            $ms = $scoresByKey->get($key);
            $attitude = $ms && $ms->attitude_score !== null ? max(0, min(100, (int) $ms->attitude_score)) : null;
            $sales = $salesByMonth[$mentee->id][$key] ?? null;
            $salesPct = ($sales !== null && $target && $target > 0)
                ? min(100, (int) round(($sales / $target) * 100))
                : null;
            $parts = array_values(array_filter([$attitude, $salesPct], fn ($v): bool => $v !== null));

            return [
                'attitude' => $attitude,
                'sales' => $sales,
                'overall' => $parts !== [] ? (int) round(array_sum($parts) / count($parts)) : null,
            ];
        }, $periods);

        $withData = array_values(array_filter(
            $rows,
            fn (array $r): bool => $r['overall'] !== null || $r['sales'] !== null || $r['attitude'] !== null,
        ));
        $latest = $withData === [] ? null : $withData[count($withData) - 1];
        $previous = count($withData) >= 2 ? $withData[count($withData) - 2] : null;
        $delta = ($latest && $previous && $latest['overall'] !== null && $previous['overall'] !== null)
            ? $latest['overall'] - $previous['overall']
            : null;

        $currentKey = sprintf('%04d-%02d', (int) $now->format('Y'), (int) $now->format('n'));
        $salesMonth = round((float) ($salesByMonth[$mentee->id][$currentKey] ?? 0), 2);
        $salesPct = ($target && $target > 0) ? min(100, (int) round(($salesMonth / $target) * 100)) : null;

        [$rank, $cohortSize] = $this->leaderboardRank($mentee, $now);

        return [
            'score' => $latest['overall'] ?? null,
            'score_delta' => $delta,
            'trend' => array_map(fn (array $r): ?int => $r['overall'], $rows),
            'sales_month' => $salesMonth,
            'sales_target' => $target !== null ? (int) $target : null,
            'sales_pct' => $salesPct,
            'rank' => $rank,
            'cohort_size' => $cohortSize,
        ];
    }

    /**
     * The host's 1-based rank within their program cohort by this-month
     * effective sales (auto live-session GMV + PIC overrides), plus the cohort
     * size. Rank is null for a solo cohort — nobody to rank against. Mirrors the
     * Performance tab's leaderboard ordering.
     *
     * @return array{0: int|null, 1: int} [rank, cohortSize]
     */
    private function leaderboardRank(LiveHostMentee $mentee, CarbonImmutable $now): array
    {
        $cohort = LiveHostMentee::query()
            ->where('program_id', $mentee->program_id)
            ->whereIn('status', ['active', 'graduated'])
            ->get(['id', 'mentee_user_id']);

        if ($cohort->count() <= 1) {
            return [null, $cohort->count()];
        }

        $totals = app(MenteeDailySalesResolver::class)
            ->rangeTotals($cohort, $now->startOfMonth(), $now->endOfMonth());

        $rank = $cohort
            ->map(fn (LiveHostMentee $m): array => ['id' => $m->id, 'sales' => $totals[$m->id] ?? 0.0])
            ->sortByDesc('sales')
            ->values()
            ->search(fn (array $r): bool => $r['id'] === $mentee->id);

        return [$rank === false ? null : $rank + 1, $cohort->count()];
    }

    /**
     * A tiny mentoring glance for the Today header: the host's current level and
     * today's effective sales (override ?? auto live-session GMV). Null when the
     * host isn't in an active mentoring program.
     *
     * @return array{level: array{name: string, color: string|null}|null, sales_today: float}|null
     */
    private function mentoringGlance(User $host, ?LiveHostMentee $mentee): ?array
    {
        if ($mentee === null) {
            return null;
        }

        $today = CarbonImmutable::now();
        $key = $today->toDateString();
        $auto = app(MenteeDailySalesResolver::class)->autoDailyGmv([$host->id], $today, $today);
        $autoGmv = (float) ($auto[$host->id][$key]['gmv'] ?? 0);
        $override = $mentee->dailyMetrics()->whereDate('metric_date', $key)->value('sales_override');
        $salesToday = $override !== null ? (float) $override : $autoGmv;

        return [
            'level' => $mentee->level ? ['name' => $mentee->level->name, 'color' => $mentee->level->color] : null,
            'sales_today' => round($salesToday, 2),
        ];
    }

    /**
     * The daily-video compliance nudge for the Today screen: whether the host
     * has logged today's video and how many. Null when the host isn't in an
     * active mentoring program (the daily-video KPI is mentee-scoped).
     *
     * @return array{logged: bool, count: int}|null
     */
    private function videoGlance(?LiveHostMentee $mentee): ?array
    {
        if ($mentee === null) {
            return null;
        }

        $count = $mentee->dailyVideos()
            ->whereDate('video_date', CarbonImmutable::now()->toDateString())
            ->count();

        return [
            'logged' => $count > 0,
            'count' => $count,
        ];
    }

    /**
     * Transition a host's live session from `live` to `ended`.
     *
     * Stamps `actual_end_at = now()` and recomputes `duration_minutes` from
     * `actual_start_at → now()`. Refuses (403) if the session belongs to a
     * different host, and (409) if the session is not currently `live`.
     */
    public function endSession(Request $request, LiveSession $session): RedirectResponse
    {
        abort_unless($session->live_host_id === $request->user()->id, 403);
        abort_unless($session->status === 'live', 409, 'Session is not currently live.');

        $session->update([
            'status' => 'ended',
            'actual_end_at' => now(),
            'duration_minutes' => $session->actual_start_at
                ? (int) $session->actual_start_at->diffInMinutes(now())
                : $session->duration_minutes,
        ]);

        return redirect()->route('live-host.dashboard')->with('success', 'Session ended.');
    }

    /**
     * @return array<string, mixed>
     */
    private function liveSessionDto(LiveSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'creatorAccount' => $session->liveAccount?->display_name ?: $session->liveAccount?->nickname,
            'platformAccount' => $session->platformAccount?->name,
            'platformType' => $session->platformAccount?->platform?->slug,
            'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
            'scheduledEndAt' => $session->scheduled_start_at && $session->duration_minutes
                ? $session->scheduled_start_at->copy()->addMinutes($session->duration_minutes)->toIso8601String()
                : null,
            'actualStartAt' => $session->actual_start_at?->toIso8601String(),
            'durationMinutes' => $session->duration_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function upcomingDto(LiveSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'creatorAccount' => $session->liveAccount?->display_name ?: $session->liveAccount?->nickname,
            'platformAccount' => $session->platformAccount?->name,
            'platformType' => $session->platformAccount?->platform?->slug,
            'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
            'durationMinutes' => $session->duration_minutes,
        ];
    }
}
