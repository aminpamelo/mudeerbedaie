<?php

declare(strict_types=1);

namespace App\Services\LiveHost;

use App\Models\LiveAccount;
use App\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the "Session Coverage" matrix: one row per creator account (punca
 * kuasa), columns of months that expand into days, each cell tallying how much
 * of that account's live activity is still un-settled — sessions that still
 * need a host upload, sessions awaiting PIC verification, sessions already
 * verified/linked to TikTok, plus TikTok lives that have no session slot yet
 * (suggestions). It's the session-lifecycle sibling of the mentoring Monthly
 * Performance grid.
 *
 * The settle-state lives on LiveSession (uploaded_at / verification_status);
 * the "no slot yet" state comes from SuggestedSlotFinder over the same window.
 */
class SessionCoverageMatrix
{
    private const TIMEZONE = 'Asia/Kuala_Lumpur';

    /** @var array<int, string> */
    private const METRIC_KEYS = ['needs_upload', 'needs_verify', 'verified', 'rejected', 'other', 'total', 'suggestions'];

    public function __construct(private readonly SuggestedSlotFinder $suggestions) {}

    /**
     * Normalize the request filters into the internal shape every method takes.
     *
     * @param  array<string, mixed>  $raw
     * @return array{hostId: ?int, platformAccountId: ?int, liveAccountId: ?int, includeUnlinked: bool}
     */
    public function filters(array $raw): array
    {
        return [
            'hostId' => isset($raw['host']) && $raw['host'] !== '' && $raw['host'] !== 'unassigned' ? (int) $raw['host'] : null,
            'platformAccountId' => isset($raw['platform_account']) && $raw['platform_account'] !== '' ? (int) $raw['platform_account'] : null,
            'liveAccountId' => isset($raw['live_account']) && $raw['live_account'] !== '' ? (int) $raw['live_account'] : null,
            'includeUnlinked' => (bool) ($raw['include_unlinked'] ?? false),
        ];
    }

    /**
     * The full matrix for a month range: account rows with a per-month cell map.
     *
     * @param  array{hostId: ?int, platformAccountId: ?int, liveAccountId: ?int, includeUnlinked: bool}  $filters
     * @return array<string, mixed>
     */
    public function monthly(int $year, int $fromMonth, int $toMonth, array $filters): array
    {
        [$fromMonth, $toMonth] = $fromMonth <= $toMonth ? [$fromMonth, $toMonth] : [$toMonth, $fromMonth];

        $start = CarbonImmutable::create($year, $fromMonth, 1)->startOfMonth();
        $end = CarbonImmutable::create($year, $toMonth, 1)->endOfMonth();

        $months = [];
        for ($m = $fromMonth; $m <= $toMonth; $m++) {
            $d = CarbonImmutable::create($year, $m, 1);
            $months[] = ['value' => $d->format('Y-m'), 'year' => $year, 'month' => $m, 'label' => $d->format('M Y')];
        }

        $accounts = $this->accountRows($filters);
        $cells = $this->cellsByAccountDate($start, $end, $filters); // [accountId => [date => metrics]]

        $accountsOut = $accounts->map(function (LiveAccount $account) use ($months, $cells) {
            $byDate = $cells[$account->id] ?? [];

            $scores = [];
            foreach ($months as $mo) {
                $scores[$mo['value']] = $this->foldMonth($byDate, $mo['value']);
            }

            $primary = $account->hosts->first();

            return [
                'id' => $account->id,
                'label' => $account->label,
                'needsReview' => (bool) $account->needs_review,
                'host' => $primary ? ['id' => $primary->id, 'name' => $primary->name, 'initials' => $this->initials($primary->name)] : null,
                'scores' => $scores,
            ];
        })->values()->all();

        return [
            'year' => $year,
            'range' => ['from' => $fromMonth, 'to' => $toMonth],
            'years' => $this->years($year),
            'months' => $months,
            'accounts' => $accountsOut,
        ];
    }

    /**
     * Per-account per-day metrics for a single month (fetched when a month is
     * expanded into its day columns).
     *
     * @param  array{hostId: ?int, platformAccountId: ?int, liveAccountId: ?int, includeUnlinked: bool}  $filters
     * @return array<string, mixed>
     */
    public function daily(int $year, int $month, array $filters): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();
        $daysInMonth = (int) $end->format('j');

        $cells = $this->cellsByAccountDate($start, $end, $filters);

        $byAccount = [];
        foreach ($cells as $accountId => $byDate) {
            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = $start->addDays($d - 1)->toDateString();
                $days[] = array_merge(['day' => $d, 'date' => $date], $this->emptyMetrics(), $byDate[$date] ?? []);
            }
            $byAccount[$accountId] = $days;
        }

        return ['by_account' => $byAccount];
    }

    /**
     * The drill-in for a single (account, day): the day's sessions with their
     * settle-state, plus the TikTok lives still lacking a slot.
     *
     * @param  array{hostId: ?int, platformAccountId: ?int, liveAccountId: ?int, includeUnlinked: bool}  $filters
     * @return array<string, mixed>
     */
    public function dayDetail(int $accountId, string $date, array $filters): array
    {
        $day = CarbonImmutable::parse($date, self::TIMEZONE);
        $start = $day->startOfDay()->subDay();
        $end = $day->endOfDay()->addDay();

        $account = LiveAccount::query()->find($accountId);

        $sessions = LiveSession::query()
            ->where('live_account_id', $accountId)
            ->whereNotNull('scheduled_start_at')
            ->whereBetween('scheduled_start_at', [$start, $end])
            ->when($filters['hostId'], fn ($q, $id) => $q->where('live_host_id', $id))
            ->when($filters['platformAccountId'], fn ($q, $id) => $q->where('platform_account_id', $id))
            ->with(['liveHost:id,name', 'platformAccount:id,name'])
            ->orderBy('scheduled_start_at')
            ->get()
            ->filter(fn (LiveSession $s) => $this->klDate($s->scheduled_start_at) === $date)
            ->map(fn (LiveSession $s) => $this->mapSessionRow($s))
            ->values()
            ->all();

        $suggestionCounts = $this->suggestions->countByAccountAndDay(
            $day->startOfDay(),
            $day->endOfDay(),
            $filters['platformAccountId'],
            $accountId,
            $filters['includeUnlinked'],
        );
        $suggestionCount = (int) ($suggestionCounts[$accountId][$date] ?? 0);

        return [
            'date' => $date,
            'account' => $account ? ['id' => $account->id, 'label' => $account->label] : ['id' => $accountId, 'label' => null],
            'sessions' => $sessions,
            'suggestionCount' => $suggestionCount,
        ];
    }

    /**
     * Aggregate a window into [accountId => [Y-m-d => metrics]]. Sessions supply
     * the settle-state buckets; SuggestedSlotFinder supplies the suggestion tally.
     *
     * @param  array{hostId: ?int, platformAccountId: ?int, liveAccountId: ?int, includeUnlinked: bool}  $filters
     * @return array<int, array<string, array<string, int>>>
     */
    private function cellsByAccountDate(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        // Pad the raw query window by a day each side, then bucket by KL date, so
        // sessions near a midnight boundary land on the correct calendar day.
        $sessions = LiveSession::query()
            ->whereNotNull('live_account_id')
            ->whereNotNull('scheduled_start_at')
            ->whereBetween('scheduled_start_at', [$start->subDay(), $end->addDay()])
            ->when($filters['hostId'], fn ($q, $id) => $q->where('live_host_id', $id))
            ->when($filters['platformAccountId'], fn ($q, $id) => $q->where('platform_account_id', $id))
            ->when($filters['liveAccountId'], fn ($q, $id) => $q->where('live_account_id', $id))
            ->get(['id', 'live_account_id', 'scheduled_start_at', 'status', 'uploaded_at', 'verification_status']);

        $cells = [];
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        foreach ($sessions as $s) {
            $date = $this->klDate($s->scheduled_start_at);
            if ($date < $startDate || $date > $endDate) {
                continue;
            }

            $accountId = (int) $s->live_account_id;
            if (! isset($cells[$accountId][$date])) {
                $cells[$accountId][$date] = $this->emptyMetrics();
            }

            $bucket = $this->bucketSession($s);
            $cells[$accountId][$date][$bucket]++;
            $cells[$accountId][$date]['total']++;
        }

        // Fold in TikTok suggestions (unlinked lives) as their own metric.
        $suggestionCounts = $this->suggestions->countByAccountAndDay(
            $start,
            $end,
            $filters['platformAccountId'],
            $filters['liveAccountId'],
            $filters['includeUnlinked'],
        );
        foreach ($suggestionCounts as $accountId => $byDate) {
            foreach ($byDate as $date => $count) {
                if (! isset($cells[$accountId][$date])) {
                    $cells[$accountId][$date] = $this->emptyMetrics();
                }
                $cells[$accountId][$date]['suggestions'] += $count;
            }
        }

        return $cells;
    }

    /**
     * Classify one session into a single settle-state bucket. Precedence keeps
     * the buckets mutually exclusive so a cell's counts sum to its session total:
     * a verified session is done; an un-uploaded past session is the host's to
     * upload (even if ended); an ended+uploaded session pending review is the
     * PIC's to verify; everything else (upcoming/live/cancelled/missed) is "other".
     */
    private function bucketSession(LiveSession $s): string
    {
        $vs = $s->verification_status ?? 'pending';
        if ($vs === 'verified') {
            return 'verified';
        }
        if ($vs === 'rejected') {
            return 'rejected';
        }

        $uploaded = $s->uploaded_at !== null;
        $isActionable = ! in_array($s->status, ['cancelled', 'missed'], true);
        $isPast = $s->scheduled_start_at !== null && $s->scheduled_start_at->isPast();

        if ($isActionable && ! $uploaded && ($s->status === 'ended' || $isPast)) {
            return 'needs_upload';
        }
        if ($s->status === 'ended' && $vs === 'pending') {
            return 'needs_verify';
        }

        return 'other';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSessionRow(LiveSession $s): array
    {
        $vs = $s->verification_status ?? 'pending';
        $uploaded = $s->uploaded_at !== null;

        return [
            'id' => $s->id,
            'title' => $s->title,
            'hostId' => $s->live_host_id,
            'hostName' => $s->liveHost?->name,
            'shop' => $s->platformAccount?->name,
            'startTime' => $s->scheduled_start_at ? $this->klTime($s->scheduled_start_at) : null,
            'status' => $s->status,
            'verificationStatus' => $vs,
            'uploaded' => $uploaded,
            'bucket' => $this->bucketSession($s),
            'gmvNet' => round(((float) ($s->gmv_amount ?? 0)) + ((float) ($s->gmv_adjustment ?? 0)), 2),
            'url' => "/livehost/sessions/{$s->id}",
        ];
    }

    /**
     * Sum a date-keyed metric map for the days belonging to a given month.
     *
     * @param  array<string, array<string, int>>  $byDate
     * @return array<string, int>
     */
    private function foldMonth(array $byDate, string $monthValue): array
    {
        $totals = $this->emptyMetrics();
        foreach ($byDate as $date => $metrics) {
            if (str_starts_with($date, $monthValue)) {
                foreach (self::METRIC_KEYS as $k) {
                    $totals[$k] += $metrics[$k] ?? 0;
                }
            }
        }

        return $totals;
    }

    /**
     * The active linked creator accounts that form the matrix rows, narrowed by
     * the filters, each with its primary operating host eager-loaded for grouping.
     *
     * @param  array{hostId: ?int, platformAccountId: ?int, liveAccountId: ?int, includeUnlinked: bool}  $filters
     * @return Collection<int, LiveAccount>
     */
    private function accountRows(array $filters): Collection
    {
        return LiveAccount::query()
            ->where('is_active', true)
            ->linked()
            ->when($filters['liveAccountId'], fn ($q, $id) => $q->where('id', $id))
            ->when($filters['hostId'], fn ($q, $id) => $q->whereHas('hosts', fn ($h) => $h->where('users.id', $id)))
            ->when($filters['platformAccountId'], fn ($q, $id) => $q->whereHas('shops', fn ($s) => $s->where('platform_accounts.id', $id)))
            ->with(['hosts' => fn ($q) => $q->orderByDesc('live_account_host.is_primary')->limit(1)])
            ->orderByRaw('COALESCE(nickname, display_name)')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    private function emptyMetrics(): array
    {
        return array_fill_keys(self::METRIC_KEYS, 0);
    }

    /**
     * @return array<int, int>
     */
    private function years(int $selected): array
    {
        $current = (int) now()->format('Y');
        $years = array_unique([$current - 1, $current, $selected]);
        sort($years);

        return array_values($years);
    }

    private function klDate(\DateTimeInterface $dt): string
    {
        return CarbonImmutable::instance($dt)->setTimezone(self::TIMEZONE)->toDateString();
    }

    private function klTime(\DateTimeInterface $dt): string
    {
        return CarbonImmutable::instance($dt)->setTimezone(self::TIMEZONE)->format('H:i');
    }

    private function initials(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '—';
        }
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$second);
    }
}
