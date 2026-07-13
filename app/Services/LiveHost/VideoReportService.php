<?php

namespace App\Services\LiveHost;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMenteeVideoComment;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shared read model for the Video Report — a host × month grid of the videos each
 * mentee logged, mirroring the Mentoring Overview: each month column can expand
 * into per-day columns. The per-video category breakdown lives in the click-in
 * drawer. Used by both the Live Host Desk (Inertia) and the CMS module (SPA) so
 * the two views never diverge.
 */
class VideoReportService
{
    /** Roles allowed to delete any comment (not just their own). */
    private const MANAGER_ROLES = ['admin', 'admin_livehost'];

    /**
     * Active programs to offer in the filter, and the subset to actually render.
     *
     * @return array{all: Collection<int, LiveHostMentoringProgram>, selected: Collection<int, LiveHostMentoringProgram>, selectedId: int|null}
     */
    public function programs(Request $request): array
    {
        $all = LiveHostMentoringProgram::query()
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'title']);

        $selectedId = $request->integer('program') ?: null;

        return [
            'all' => $all,
            'selected' => $selectedId ? $all->where('id', $selectedId)->values() : $all,
            'selectedId' => $selectedId,
        ];
    }

    /**
     * The host × month matrix payload for a set of programs.
     *
     * @param  Collection<int, LiveHostMentoringProgram>  $programs
     * @param  array{start: string, end: string, meta: array<string, mixed>}  $window
     * @return array{months: array<int, array{key: string, label: string, year: int, month: int}>, programs: array<int, mixed>}
     */
    public function matrix(Collection $programs, array $window): array
    {
        $mentees = $this->menteesFor($programs);
        $aggregates = $this->videoAggregates($mentees->pluck('id'), $window);
        $months = $this->windowMonths($window);
        $byProgram = $mentees->groupBy('program_id');

        $programPayload = $programs->map(function (LiveHostMentoringProgram $program) use ($byProgram, $aggregates, $months) {
            $rows = ($byProgram->get($program->id) ?? collect())
                ->map(function (LiveHostMentee $m) use ($aggregates, $months) {
                    $agg = $aggregates[$m->id] ?? null;
                    $counts = [];
                    foreach ($months as $mo) {
                        $counts[$mo['key']] = (int) ($agg['months'][$mo['key']] ?? 0);
                    }

                    return [
                        'mentee_id' => $m->id,
                        'name' => $m->menteeUser?->name ?? 'Host #'.$m->id,
                        'initials' => self::initials($m->menteeUser?->name),
                        'status' => $m->status,
                        'counts' => $counts,
                        'total' => (int) ($agg['total'] ?? 0),
                        'commented' => (int) ($agg['commented'] ?? 0),
                        'awaiting_reply' => (int) ($agg['awaiting_reply'] ?? 0),
                    ];
                })
                ->values();

            $totals = [];
            foreach ($months as $mo) {
                $totals[$mo['key']] = (int) $rows->sum(fn ($r) => $r['counts'][$mo['key']] ?? 0);
            }

            return [
                'id' => $program->id,
                'title' => $program->title,
                'hosts' => $rows,
                'totals' => $totals,
                'grand_total' => (int) $rows->sum('total'),
            ];
        })->values()->all();

        return ['months' => $months, 'programs' => $programPayload];
    }

    /**
     * Per-mentee per-day video counts for one month (drives a month's day-column
     * expansion across every row at once).
     *
     * @param  Collection<int, LiveHostMentoringProgram>  $programs
     * @return array{month: string, label: string, days: array<int, array{day: int, dow: string}>, counts: array<int, array<int, int>>}
     */
    public function dayMatrix(Collection $programs, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $days = collect(range(1, $start->daysInMonth))
            ->map(fn ($d) => ['day' => $d, 'dow' => CarbonImmutable::create($year, $month, $d)->format('D')])
            ->values()
            ->all();

        $menteeIds = $this->menteesFor($programs)->pluck('id');
        $counts = [];
        if ($menteeIds->isNotEmpty()) {
            $videos = LiveHostMenteeDailyVideo::query()
                ->whereIn('mentee_id', $menteeIds)
                ->whereBetween('video_date', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->get(['mentee_id', 'video_date']);

            foreach ($videos as $v) {
                $mid = (int) $v->mentee_id;
                $day = (int) $v->video_date->format('j');
                $counts[$mid][$day] = ($counts[$mid][$day] ?? 0) + 1;
            }
        }

        return [
            'month' => sprintf('%04d-%02d', $year, $month),
            'label' => $start->format('M Y'),
            'days' => $days,
            'counts' => (object) $counts,
        ];
    }

    /**
     * The videos for one host over a date range (a month cell, a single day cell,
     * or the whole window), each with its full comment thread.
     *
     * @return array{host: array<string, mixed>, period: array{label: string}, videos: array<int, mixed>}
     */
    public function cell(LiveHostMentee $mentee, string $start, string $end, string $label, ?User $viewer): array
    {
        $videos = LiveHostMenteeDailyVideo::query()
            ->where('mentee_id', $mentee->id)
            ->whereBetween('video_date', [$start, $end])
            ->with(['comments.user:id,name,role'])
            ->orderByDesc('video_date')
            ->orderByDesc('id')
            ->get();

        return [
            'host' => ['mentee_id' => $mentee->id, 'name' => $mentee->menteeUser?->name],
            'period' => ['label' => $label],
            'videos' => $videos->map(fn (LiveHostMenteeDailyVideo $v) => $this->serializeVideo($v, $viewer))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeVideo(LiveHostMenteeDailyVideo $video, ?User $viewer): array
    {
        $canManageAny = $viewer !== null && in_array($viewer->role, self::MANAGER_ROLES, true);

        return [
            'id' => $video->id,
            'title' => $video->title,
            'category' => $video->category,
            'category_label' => $video->categoryLabel(),
            'link' => $video->link,
            'date' => $video->video_date?->toDateString(),
            'date_label' => $video->video_date?->format('j M Y'),
            'comments' => $video->comments
                ->sortBy('created_at')
                ->map(fn (LiveHostMenteeVideoComment $c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author_role' => $c->author_role,
                    'is_host' => $c->isFromHost(),
                    'author' => [
                        'name' => $c->user?->name,
                        'initials' => self::initials($c->user?->name),
                    ],
                    'created_human' => $c->created_at?->diffForHumans(),
                    'can_delete' => $viewer !== null && ($c->user_id === $viewer->id || $canManageAny),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Mentees (active + graduated) of the given programs, surfacing active first.
     *
     * @param  Collection<int, LiveHostMentoringProgram>  $programs
     * @return Collection<int, LiveHostMentee>
     */
    private function menteesFor(Collection $programs): Collection
    {
        return LiveHostMentee::query()
            ->whereIn('program_id', $programs->pluck('id'))
            ->whereIn('status', ['active', 'graduated'])
            ->with('menteeUser:id,name')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->get();
    }

    /**
     * Per-mentee aggregates over the window: video counts per month, total, how
     * many videos have any comment, and how many are awaiting a staff reply.
     *
     * @param  Collection<int, int>  $menteeIds
     * @param  array{start: string, end: string}  $window
     * @return array<int, array{months: array<string, int>, total: int, commented: int, awaiting_reply: int}>
     */
    private function videoAggregates(Collection $menteeIds, array $window): array
    {
        if ($menteeIds->isEmpty()) {
            return [];
        }

        $videos = LiveHostMenteeDailyVideo::query()
            ->whereIn('mentee_id', $menteeIds)
            ->whereBetween('video_date', [$window['start'], $window['end']])
            ->get(['id', 'mentee_id', 'video_date']);

        if ($videos->isEmpty()) {
            return [];
        }

        $latestRoleByVideo = LiveHostMenteeVideoComment::query()
            ->whereIn('video_id', $videos->pluck('id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['video_id', 'author_role'])
            ->groupBy('video_id')
            ->map(fn (Collection $rows) => $rows->last()->author_role);

        $out = [];
        foreach ($videos->groupBy('mentee_id') as $menteeId => $rows) {
            $commented = 0;
            $awaiting = 0;
            foreach ($rows as $v) {
                $latestRole = $latestRoleByVideo->get($v->id);
                if ($latestRole !== null) {
                    $commented++;
                    if ($latestRole === 'host') {
                        $awaiting++;
                    }
                }
            }

            $out[(int) $menteeId] = [
                'months' => $rows->countBy(fn (LiveHostMenteeDailyVideo $v) => $v->video_date->format('Y-m'))->all(),
                'total' => $rows->count(),
                'commented' => $commented,
                'awaiting_reply' => $awaiting,
            ];
        }

        return $out;
    }

    /**
     * The month columns for the window (from..to of the chosen year).
     *
     * @param  array{meta: array<string, mixed>}  $window
     * @return array<int, array{key: string, label: string, year: int, month: int}>
     */
    private function windowMonths(array $window): array
    {
        $year = (int) $window['meta']['year'];

        return collect(range($window['meta']['from'], $window['meta']['to']))
            ->map(fn ($m) => [
                'key' => sprintf('%04d-%02d', $year, $m),
                'label' => CarbonImmutable::create($year, (int) $m, 1)->format('M'),
                'year' => $year,
                'month' => (int) $m,
            ])
            ->values()
            ->all();
    }

    /**
     * The month window (year + from/to) the matrix aggregates over, defaulting to
     * the last six months up to the current month.
     *
     * @return array{start: string, end: string, meta: array<string, mixed>}
     */
    public function window(Request $request): array
    {
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        $year = $request->integer('year') ?: $currentYear;
        $defaultTo = $year === $currentYear ? $currentMonth : 12;
        $to = $request->integer('to') ?: $defaultTo;
        $from = $request->integer('from') ?: max(1, $to - 5);

        $from = max(1, min(12, $from));
        $to = max(1, min(12, $to));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $start = CarbonImmutable::create($year, $from, 1)->startOfMonth();
        $end = CarbonImmutable::create($year, $to, 1)->endOfMonth();

        return [
            // Full datetimes so BETWEEN is inclusive of a datetime video_date on
            // the last day of the window (a date-only bound would drop it).
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
            'meta' => [
                'year' => $year,
                'from' => $from,
                'to' => $to,
                'label' => $start->format('M Y').' – '.$end->format('M Y'),
                'years' => range($currentYear - 2, max($currentYear, $year)),
            ],
        ];
    }

    private static function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last) ?: '?';
    }
}
