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
 * Shared read model for the Video Report — the host × content-category matrix of
 * host-logged videos and their comment threads. Used by both the Live Host Desk
 * (Inertia) and the CMS module (React SPA) so the two views never diverge.
 *
 * Videos with no (or an unrecognised) category fall into an "Uncategorised"
 * bucket so they stay visible instead of silently vanishing from the grid.
 */
class VideoReportService
{
    public const UNCATEGORISED = '__uncat__';

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
     * The host × category matrix payload for a set of programs.
     *
     * @param  Collection<int, LiveHostMentoringProgram>  $programs
     * @param  array{start: string, end: string}  $window
     * @return array{programs: array<int, mixed>, categories: array<int, array{slug: string, label: string}>}
     */
    public function matrix(Collection $programs, array $window): array
    {
        $mentees = LiveHostMentee::query()
            ->whereIn('program_id', $programs->pluck('id'))
            ->whereIn('status', ['active', 'graduated'])
            ->with('menteeUser:id,name')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->get();

        $aggregates = $this->videoAggregates($mentees->pluck('id'), $window);

        // The 5 fixed categories, plus an Uncategorised column only when some
        // in-view video actually lacks a recognised category.
        $categories = collect(LiveHostMenteeDailyVideo::CATEGORIES)
            ->map(fn (string $label, string $slug) => ['slug' => $slug, 'label' => $label])
            ->values()
            ->all();

        $hasUncat = collect($aggregates)->contains(fn ($a) => ($a['counts'][self::UNCATEGORISED] ?? 0) > 0);
        if ($hasUncat) {
            $categories[] = ['slug' => self::UNCATEGORISED, 'label' => 'Uncategorised'];
        }

        $byProgram = $mentees->groupBy('program_id');

        $programPayload = $programs->map(function (LiveHostMentoringProgram $program) use ($byProgram, $aggregates, $categories) {
            $rows = ($byProgram->get($program->id) ?? collect())
                ->map(function (LiveHostMentee $m) use ($aggregates, $categories) {
                    $agg = $aggregates[$m->id] ?? null;
                    $counts = [];
                    foreach ($categories as $c) {
                        $counts[$c['slug']] = (int) ($agg['counts'][$c['slug']] ?? 0);
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
            foreach ($categories as $c) {
                $totals[$c['slug']] = (int) $rows->sum(fn ($r) => $r['counts'][$c['slug']] ?? 0);
            }

            return [
                'id' => $program->id,
                'title' => $program->title,
                'hosts' => $rows,
                'totals' => $totals,
                'grand_total' => (int) $rows->sum('total'),
            ];
        })->values()->all();

        return ['programs' => $programPayload, 'categories' => $categories];
    }

    /**
     * The videos in one matrix cell (host + category over the window), each with
     * its full comment thread.
     *
     * @param  array{start: string, end: string}  $window
     * @return array{host: array<string, mixed>, category: array{slug: string, label: string}, videos: array<int, mixed>}
     */
    public function cell(LiveHostMentee $mentee, string $category, array $window, ?User $viewer): array
    {
        $known = array_keys(LiveHostMenteeDailyVideo::CATEGORIES);

        $videos = LiveHostMenteeDailyVideo::query()
            ->where('mentee_id', $mentee->id)
            ->when(
                $category === self::UNCATEGORISED,
                fn ($q) => $q->where(fn ($w) => $w->whereNull('category')->orWhereNotIn('category', $known)),
                fn ($q) => $q->when($category !== '', fn ($qq) => $qq->where('category', $category)),
            )
            ->whereBetween('video_date', [$window['start'], $window['end']])
            ->with(['comments.user:id,name,role'])
            ->orderByDesc('video_date')
            ->orderByDesc('id')
            ->get();

        return [
            'host' => ['mentee_id' => $mentee->id, 'name' => $mentee->menteeUser?->name],
            'category' => [
                'slug' => $category,
                'label' => $this->categoryLabel($category),
            ],
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
     * Per-mentee aggregates over the window: counts per category (unknown →
     * Uncategorised), total, commented count, and awaiting-reply count (latest
     * comment came from the host).
     *
     * @param  Collection<int, int>  $menteeIds
     * @param  array{start: string, end: string}  $window
     * @return array<int, array{counts: array<string, int>, total: int, commented: int, awaiting_reply: int}>
     */
    private function videoAggregates(Collection $menteeIds, array $window): array
    {
        if ($menteeIds->isEmpty()) {
            return [];
        }

        $videos = LiveHostMenteeDailyVideo::query()
            ->whereIn('mentee_id', $menteeIds)
            ->whereBetween('video_date', [$window['start'], $window['end']])
            ->get(['id', 'mentee_id', 'category']);

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
                'counts' => $rows->countBy(fn (LiveHostMenteeDailyVideo $v) => $this->categoryKey($v->category))->all(),
                'total' => $rows->count(),
                'commented' => $commented,
                'awaiting_reply' => $awaiting,
            ];
        }

        return $out;
    }

    private function categoryKey(?string $category): string
    {
        return $category !== null && array_key_exists($category, LiveHostMenteeDailyVideo::CATEGORIES)
            ? $category
            : self::UNCATEGORISED;
    }

    private function categoryLabel(string $slug): string
    {
        if ($slug === self::UNCATEGORISED) {
            return 'Uncategorised';
        }

        return LiveHostMenteeDailyVideo::CATEGORIES[$slug] ?? 'All categories';
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
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'meta' => [
                'year' => $year,
                'from' => $from,
                'to' => $to,
                'label' => $start->format('M Y').' – '.$end->format('M Y'),
                'years' => range($currentYear - 2, max($currentYear, $year)),
                'months' => collect(range(1, 12))
                    ->map(fn ($m) => ['value' => $m, 'label' => CarbonImmutable::create($year, $m, 1)->format('M')])
                    ->values()
                    ->all(),
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
