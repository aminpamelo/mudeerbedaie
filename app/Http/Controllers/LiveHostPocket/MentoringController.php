<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyComment;
use App\Models\LiveHostMentoringLevel;
use App\Models\User;
use App\Services\Mentoring\MenteeDailySalesResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — "My Path".
 *
 * The host's own view of their mentoring journey: current stage, performance
 * level, task checklist, and the path toward becoming a top host. Read-only —
 * the PIC/top-host drives changes from the Live Host Desk.
 */
class MentoringController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        $eagerLoad = [
            'program.stages' => fn ($q) => $q->orderBy('position'),
            'program.leader:id,name',
            'mentor:id,name',
            'currentStage',
            'level',
            'checklistItems' => fn ($q) => $q->orderBy('position'),
            'activities' => fn ($q) => $q->latest('occurred_at')->limit(10),
            'monthlyScores' => fn ($q) => $q->orderBy('year')->orderBy('month'),
        ];

        $mentee = $this->resolveMentee($user, $eagerLoad);

        if ($mentee === null) {
            return Inertia::render('MyPath', ['enrollment' => null, 'leaderboard' => null]);
        }

        $currentPosition = $mentee->currentStage?->position ?? 0;
        $stages = $mentee->program->stages->map(fn ($s) => [
            'name' => $s->name,
            'position' => (int) $s->position,
            'is_final' => (bool) $s->is_final,
            'state' => $s->position < $currentPosition ? 'done' : ($s->position === $currentPosition ? 'current' : 'upcoming'),
        ])->values();

        $stageTotal = $stages->count();
        $stageProgress = [
            'current_position' => $currentPosition,
            'total' => $stageTotal,
            'pct' => $stageTotal > 0 ? (int) round(($currentPosition / $stageTotal) * 100) : 0,
        ];

        $performance = $this->performanceData($mentee);

        $checklist = $mentee->checklistItems;
        $done = $checklist->where('status', 'done')->count();
        $total = $checklist->count();

        $mapTask = fn ($c) => [
            'title' => $c->title,
            'description' => $c->description,
            'is_required' => (bool) $c->is_required,
            'status' => $c->status,
            'due_at_human' => $c->due_at?->diffForHumans(),
            'is_overdue' => $c->due_at && $c->due_at->isPast() && $c->status !== 'done',
        ];

        $programTasks = $checklist->where('source', '!=', 'custom');
        $individualTasks = $checklist->where('source', 'custom');

        // Level ladder — every active level with the mentee's progress marked.
        $currentLevel = $mentee->level;
        $ladder = LiveHostMentoringLevel::query()->active()->orderBy('position')->get()
            ->map(function (LiveHostMentoringLevel $l) use ($currentLevel) {
                $state = 'upcoming';
                if ($currentLevel) {
                    if ($l->id === $currentLevel->id) {
                        $state = 'current';
                    } elseif ($l->position < $currentLevel->position) {
                        $state = 'achieved';
                    }
                }

                return [
                    'name' => $l->name,
                    'color' => $l->color,
                    'is_top' => (bool) $l->is_top,
                    'state' => $state,
                ];
            })->values();

        $mentorName = $mentee->mentor?->name ?? $mentee->program->leader?->name;

        return Inertia::render('MyPath', [
            'enrollment' => [
                'mentee_number' => $mentee->mentee_number,
                'status' => $mentee->status,
                'enrolled_at_human' => $mentee->enrolled_at?->diffForHumans(),
                'program' => ['title' => $mentee->program->title],
                'mentor' => $mentorName ? ['name' => $mentorName] : null,
                'current_stage' => $mentee->currentStage ? [
                    'name' => $mentee->currentStage->name,
                    'is_final' => (bool) $mentee->currentStage->is_final,
                ] : null,
                'level' => $currentLevel ? ['name' => $currentLevel->name, 'color' => $currentLevel->color] : null,
                'stages' => $stages,
                'stage_progress' => $stageProgress,
                'performance' => $performance,
                'ladder' => $ladder,
                'checklist' => [
                    'done' => $done,
                    'total' => $total,
                    'pct' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
                    'program' => $programTasks->map($mapTask)->values(),
                    'individual' => $individualTasks->map($mapTask)->values(),
                    'individual_done' => $individualTasks->where('status', 'done')->count(),
                    'individual_total' => $individualTasks->count(),
                ],
                'activities' => $mentee->activities->map(fn ($a) => [
                    'type' => $a->type,
                    'title' => $a->title,
                    'occurred_at_human' => $a->occurred_at?->diffForHumans(),
                ])->values(),
                'daily' => $this->dailyData($mentee),
                'available_months' => $this->availableMonths($mentee),
                'comments' => $this->commentsData($mentee),
                'conduct' => $this->conductData($mentee),
            ],
            'leaderboard' => $this->leaderboardData($mentee),
        ]);
    }

    /**
     * JSON: the day-by-day sales strip for a chosen month, so the host can browse
     * past months. The requested period is clamped to the enrolment window
     * (earliest enrolment month → current month) to keep it within real data.
     */
    public function daily(Request $request): JsonResponse
    {
        $mentee = $this->resolveMentee($request->user(), []);
        abort_if($mentee === null, 403);

        $now = CarbonImmutable::now();
        $start = $mentee->enrolled_at
            ? CarbonImmutable::parse($mentee->enrolled_at)->startOfMonth()
            : $now->startOfMonth();

        $year = $request->integer('year') ?: (int) $now->format('Y');
        $month = $request->integer('month') ?: (int) $now->format('n');
        $requested = CarbonImmutable::create($year, max(1, min(12, $month)), 1);

        // Clamp into [enrolment start month, current month].
        if ($requested->lessThan($start)) {
            $requested = $start;
        } elseif ($requested->greaterThan($now->startOfMonth())) {
            $requested = $now->startOfMonth();
        }

        return response()->json($this->dailyData($mentee, (int) $requested->format('Y'), (int) $requested->format('n')));
    }

    /**
     * Prefer the active enrollment; fall back to the most recent graduated one so
     * a host who finished the program still sees their performance history.
     *
     * @param  array<string, mixed>  $eagerLoad
     */
    private function resolveMentee(User $user, array $eagerLoad): ?LiveHostMentee
    {
        return $user->activeMenteeEnrollment()->with($eagerLoad)->first()
            ?? $user->menteeEnrollments()->with($eagerLoad)
                ->where('status', 'graduated')
                ->latest('enrolled_at')
                ->first();
    }

    /**
     * Selectable months for the day-by-day browser: every month from the host's
     * enrolment through the current month, newest first.
     *
     * @return list<array{year: int, month: int, label: string}>
     */
    private function availableMonths(LiveHostMentee $mentee): array
    {
        $now = CarbonImmutable::now()->startOfMonth();
        $cursor = $mentee->enrolled_at
            ? CarbonImmutable::parse($mentee->enrolled_at)->startOfMonth()
            : $now;

        $months = [];
        while ($cursor->lessThanOrEqualTo($now) && count($months) < 60) {
            $months[] = [
                'year' => (int) $cursor->format('Y'),
                'month' => (int) $cursor->format('n'),
                'label' => $cursor->format('M Y'),
            ];
            $cursor = $cursor->addMonth();
        }

        return array_reverse($months);
    }

    /**
     * Cohort sales leaderboard — the host and their program peers ranked by
     * effective sales (auto live-session GMV + PIC overrides). Two windows are
     * pre-computed so the front end can toggle "This month" / "All time" without
     * a round trip. Scoped to the host's own program only; dropped mentees are
     * excluded so the board reflects the live cohort.
     *
     * @return array{program_title: string, member_count: int, my_mentee_id: int, periods: array<string, array{key: string, label: string, rows: list<array<string, mixed>>}>}
     */
    private function leaderboardData(LiveHostMentee $mentee): array
    {
        $cohort = LiveHostMentee::query()
            ->where('program_id', $mentee->program_id)
            ->whereIn('status', ['active', 'graduated'])
            ->with(['menteeUser:id,name,avatar_path', 'level:id,name,color,position'])
            ->get();

        $now = CarbonImmutable::now();
        $earliest = $cohort->min('enrolled_at');
        $allFrom = $earliest
            ? CarbonImmutable::parse($earliest)->startOfMonth()
            : $now->subMonths(11)->startOfMonth();

        $resolver = app(MenteeDailySalesResolver::class);
        $monthTotals = $resolver->rangeTotals($cohort, $now->startOfMonth(), $now->endOfMonth());
        $allTotals = $resolver->rangeTotals($cohort, $allFrom, $now->endOfMonth());

        $buildRows = fn (array $totals): array => $cohort
            ->map(fn (LiveHostMentee $m): array => [
                'mentee_id' => $m->id,
                'name' => $m->menteeUser?->name ?? 'Host',
                'avatar_url' => $m->menteeUser?->avatar_url,
                'level' => $m->level ? ['name' => $m->level->name, 'color' => $m->level->color] : null,
                'sales' => $totals[$m->id] ?? 0.0,
                'is_me' => $m->id === $mentee->id,
            ])
            ->sortByDesc('sales')
            ->values()
            ->map(function (array $row, int $i): array {
                $row['rank'] = $i + 1;

                return $row;
            })
            ->values()
            ->all();

        return [
            'program_title' => $mentee->program->title,
            'member_count' => $cohort->count(),
            'my_mentee_id' => $mentee->id,
            'periods' => [
                'this_month' => ['key' => 'this_month', 'label' => $now->format('F Y'), 'rows' => $buildRows($monthTotals)],
                'all_time' => ['key' => 'all_time', 'label' => 'All time', 'rows' => $buildRows($allTotals)],
            ],
        ];
    }

    /**
     * The host's monthly performance: latest score, a 6-month trend, and the
     * change vs the previous month. Sales are the SUM of the host's effective
     * daily sales (auto live-session GMV + PIC overrides) — the same figure the
     * PIC sees. Attitude remains the PIC's monthly rating. The Overall KPI is the
     * mean of Attitude (0-100) and Sales% (sales ÷ the level's monthly target).
     *
     * @return array{has_scores: bool, sales_target: int|null, latest: array<string, mixed>|null, trend: list<array<string, mixed>>, delta_overall: int|null}
     */
    private function performanceData(LiveHostMentee $mentee): array
    {
        $target = $mentee->level?->monthly_sales_target;
        $now = CarbonImmutable::now();

        $periods = collect(range(5, 0))
            ->map(fn ($i) => $now->subMonths($i))
            ->map(fn ($d) => ['year' => (int) $d->format('Y'), 'month' => (int) $d->format('n')])
            ->values()
            ->all();

        $salesTotals = app(MenteeDailySalesResolver::class)->monthlyTotals(collect([$mentee]), $periods);
        $byKey = $mentee->monthlyScores->keyBy(fn ($s) => sprintf('%04d-%02d', $s->year, $s->month));

        $scores = collect($periods)->map(function ($p) use ($target, $salesTotals, $byKey, $mentee): array {
            $key = sprintf('%04d-%02d', $p['year'], $p['month']);
            $ms = $byKey->get($key);
            $attitude = $ms && $ms->attitude_score !== null ? max(0, min(100, (int) $ms->attitude_score)) : null;
            $sales = $salesTotals[$mentee->id][$key] ?? null;
            $salesPct = ($sales !== null && $target && $target > 0)
                ? min(100, (int) round(($sales / $target) * 100))
                : null;

            $parts = array_values(array_filter([$attitude, $salesPct], fn ($v) => $v !== null));
            $overall = $parts !== [] ? (int) round(array_sum($parts) / count($parts)) : null;

            return [
                'period' => $key,
                'year' => $p['year'],
                'month' => $p['month'],
                'attitude' => $attitude,
                'sales' => $sales,
                'sales_pct' => $salesPct,
                'overall' => $overall,
            ];
        });

        $withData = $scores->filter(fn ($s) => $s['overall'] !== null || $s['sales'] !== null || $s['attitude'] !== null)->values();
        $latest = $withData->last();
        $previous = $withData->count() >= 2 ? $withData->slice(-2, 1)->first() : null;
        $delta = ($latest && $previous && $latest['overall'] !== null && $previous['overall'] !== null)
            ? $latest['overall'] - $previous['overall']
            : null;

        return [
            'has_scores' => $withData->isNotEmpty(),
            'sales_target' => $target !== null ? (int) $target : null,
            'latest' => $latest,
            'trend' => $scores->slice(-6)->values()->all(),
            'delta_overall' => $delta,
        ];
    }

    /**
     * The host's own daily sales strip for a month (the current month by default),
     * plus the month total. The current month caps at today; past months show
     * every day. Sales are the effective daily figure (override ?? auto GMV).
     *
     * @return array{year: int, month: int, month_label: string, total: float, days: Collection<int, array<string, mixed>>}
     */
    private function dailyData(LiveHostMentee $mentee, ?int $year = null, ?int $month = null): array
    {
        $now = CarbonImmutable::now();
        $year ??= (int) $now->format('Y');
        $month ??= (int) $now->format('n');
        $period = CarbonImmutable::create($year, $month, 1);

        $isCurrentMonth = $period->format('Y-m') === $now->format('Y-m');
        $lastDay = $isCurrentMonth ? (int) $now->format('j') : $period->daysInMonth;

        $discByDate = $mentee->disciplinaryRecords()
            ->whereBetween('incident_date', [$period->startOfMonth()->toDateString(), $period->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn ($r) => $r->incident_date->toDateString());

        $days = collect(app(MenteeDailySalesResolver::class)->dailyBreakdown($mentee, $year, $month))
            ->filter(fn ($d) => $d['day'] <= $lastDay)
            ->map(fn ($d) => [
                'date' => $d['date'],
                'day' => $d['day'],
                'sales' => $d['effective'],
                'sessions' => $d['sessions'],
                'has_comment' => $d['has_comment'],
                'has_disciplinary' => $discByDate->has($d['date']),
            ])
            ->values();

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => $period->format('F Y'),
            'total' => round((float) $days->sum('sales'), 2),
            'days' => $days,
        ];
    }

    /**
     * Recent PIC daily comments (the host's feedback loop) — the daily activity
     * log. Comments are grouped by author, so several people may have commented
     * on the same day; each is listed with its author.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function commentsData(LiveHostMentee $mentee): Collection
    {
        return $mentee->dailyComments()
            ->with('user:id,name')
            ->orderByDesc('metric_date')
            ->orderByDesc('created_at')
            ->limit(14)
            ->get()
            ->map(fn (LiveHostMenteeDailyComment $c) => [
                'date' => $c->metric_date?->toDateString(),
                'date_human' => $c->metric_date?->format('M j'),
                'comment' => $c->comment,
                'by' => $c->user?->name,
            ])
            ->values();
    }

    /**
     * The host's own disciplinary / conduct records (read-only).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function conductData(LiveHostMentee $mentee): Collection
    {
        return $mentee->disciplinaryRecords()
            ->get()
            ->map(fn ($r) => [
                'incident_date_human' => $r->incident_date?->format('M j, Y'),
                'category' => $r->category,
                'severity' => $r->severity,
                'description' => $r->description,
            ])
            ->values();
    }
}
