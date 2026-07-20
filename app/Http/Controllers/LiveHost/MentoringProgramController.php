<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\Mentoring\MentoringProgramRequest;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\MenteeBoardPresenter;
use App\Services\Mentoring\MenteeDailySalesResolver;
use App\Services\Mentoring\MentorActivityIndicator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MentoringProgramController extends Controller
{
    /**
     * Request-level memo for the program-independent grid lookups (assignable
     * PICs + active levels). The overview builds one grid per active program, so
     * without this each program would re-run these identical global queries.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $assignablePicsCache = null;

    /** @var Collection<int, array{id: int, name: string, color: ?string}>|null */
    private ?Collection $activeLevelsCache = null;

    public function index(Request $request): Response
    {
        $showArchived = $request->query('view') === 'archived';

        $paginator = LiveHostMentoringProgram::query()
            ->archived($showArchived)
            ->with('leader:id,name')
            ->withCount([
                'mentees',
                'mentees as active_mentees_count' => fn ($q) => $q->where('status', 'active'),
                'mentees as graduated_mentees_count' => fn ($q) => $q->where('status', 'graduated'),
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $indicators = app(MentorActivityIndicator::class)
            ->forLeaders($paginator->getCollection()->pluck('leader_user_id')->all());

        $programs = $paginator->through(fn (LiveHostMentoringProgram $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'status' => $p->status,
            'archived' => $p->archived_at !== null,
            'archived_at' => $p->archived_at?->toIso8601String(),
            'leader' => $p->leader ? [
                'id' => $p->leader->id,
                'name' => $p->leader->name,
                'initials' => self::initials($p->leader->name),
            ] : null,
            'activity' => $p->leader_user_id
                ? ($indicators[$p->leader_user_id] ?? ['level' => 'red', 'label' => 'Inactive', 'lastAt' => null, 'count30' => 0])
                : ['level' => 'none', 'label' => 'No leader', 'lastAt' => null, 'count30' => 0],
            'mentees_count' => (int) ($p->mentees_count ?? 0),
            'active_mentees_count' => (int) ($p->active_mentees_count ?? 0),
            'graduated_mentees_count' => (int) ($p->graduated_mentees_count ?? 0),
            'starts_at' => $p->starts_at?->toIso8601String(),
            'ends_at' => $p->ends_at?->toIso8601String(),
        ]);

        return Inertia::render('mentoring/programs/Index', [
            'programs' => $programs,
            'view' => $showArchived ? 'archived' : 'active',
            'archivedCount' => LiveHostMentoringProgram::query()->archived()->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('mentoring/programs/Create', [
            'assignableLeaders' => $this->assignableLeaders(),
        ]);
    }

    public function store(MentoringProgramRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? '') ?: $this->uniqueSlugFor($data['title']);
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        $program = LiveHostMentoringProgram::create($data);

        return redirect()
            ->route('livehost.mentoring.programs.edit', $program)
            ->with('success', "Program \"{$program->title}\" created. Configure stages, then activate.");
    }

    public function show(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        return redirect()->route('livehost.mentoring.programs.edit', $program);
    }

    public function edit(Request $request, LiveHostMentoringProgram $program): Response
    {
        $program->load('leader:id,name');
        $program->loadCount([
            'mentees',
            'mentees as active_mentees_count' => fn ($q) => $q->where('status', 'active'),
        ]);

        return Inertia::render('mentoring/programs/Edit', [
            'program' => [
                'id' => $program->id,
                'title' => $program->title,
                'slug' => $program->slug,
                'description' => $program->description,
                'status' => $program->status,
                'leader_user_id' => $program->leader_user_id,
                'leader' => $program->leader ? [
                    'id' => $program->leader->id,
                    'name' => $program->leader->name,
                    'initials' => self::initials($program->leader->name),
                ] : null,
                'starts_at' => $program->starts_at?->toIso8601String(),
                'ends_at' => $program->ends_at?->toIso8601String(),
                'mentees_count' => (int) ($program->mentees_count ?? 0),
                'active_mentees_count' => (int) ($program->active_mentees_count ?? 0),
                'checklist_template' => $program->checklist_template ?? [],
            ],
            'stages' => $program->stages()->orderBy('position')->get()->map(fn ($s) => [
                'id' => $s->id,
                'position' => (int) $s->position,
                'name' => $s->name,
                'description' => $s->description,
                'is_final' => (bool) $s->is_final,
                'mentees_count' => $s->mentees()->count(),
            ])->values(),
            'assignableLeaders' => $this->assignableLeaders(),
            'activityIndicator' => app(MentorActivityIndicator::class)->forLeader($program->leader_user_id),
            'activities' => $program->activities()
                ->with(['mentee.menteeUser:id,name', 'creator:id,name'])
                ->latest('occurred_at')
                ->limit(25)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'title' => $a->title,
                    'notes' => $a->notes,
                    'occurred_at' => $a->occurred_at?->toIso8601String(),
                    'occurred_at_human' => $a->occurred_at?->diffForHumans(),
                    'mentee' => $a->mentee?->menteeUser ? [
                        'id' => $a->mentee->id,
                        'name' => $a->mentee->menteeUser->name,
                    ] : null,
                    'created_by' => $a->creator?->name,
                ])->values(),
            'mentees' => $program->mentees()
                ->where('status', 'active')
                ->with('menteeUser:id,name')
                ->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->menteeUser?->name])
                ->values(),
            'performance' => $this->performanceData($program, $request),
            'board' => app(MenteeBoardPresenter::class)->forProgram($program),
        ]);
    }

    /**
     * Per-mentee checklist monitoring matrix for a program: the template task
     * titles become the columns; every active mentee is a row showing which of
     * those tasks they've completed, plus their own individual-task progress and
     * an overall completion percentage.
     */
    public function checklistOverview(LiveHostMentoringProgram $program): JsonResponse
    {
        $columns = collect($program->checklist_template ?? [])
            ->pluck('title')
            ->filter()
            ->values();

        $mentees = $program->mentees()
            ->where('status', 'active')
            ->with(['menteeUser:id,name', 'currentStage:id,name', 'checklistItems'])
            ->get()
            ->map(function (LiveHostMentee $m) use ($columns) {
                $items = $m->checklistItems;
                $templateByTitle = $items->where('source', '!=', 'custom')->keyBy('title');
                $custom = $items->where('source', 'custom');

                $cells = $columns->mapWithKeys(function ($title) use ($templateByTitle) {
                    $item = $templateByTitle->get($title);

                    return [$title => $item ? ['id' => $item->id, 'status' => $item->status] : null];
                });

                $done = $items->where('status', 'done')->count();
                $total = $items->count();

                return [
                    'id' => $m->id,
                    'name' => $m->menteeUser?->name,
                    'mentee_number' => $m->mentee_number,
                    'stage' => $m->currentStage?->name,
                    'done' => $done,
                    'total' => $total,
                    'pct' => $total ? (int) round($done / $total * 100) : 0,
                    'cells' => $cells,
                    'custom_done' => $custom->where('status', 'done')->count(),
                    'custom_total' => $custom->count(),
                    'custom_tasks' => $custom->sortBy('position')->values()->map(fn ($c) => [
                        'id' => $c->id,
                        'title' => $c->title,
                        'description' => $c->description,
                        'is_required' => (bool) $c->is_required,
                        'status' => $c->status,
                        'due_at' => $c->due_at?->format('Y-m-d'),
                        'due_at_human' => $c->due_at?->diffForHumans(),
                        'is_overdue' => $c->due_at && $c->due_at->isPast() && $c->status !== 'done',
                    ])->all(),
                ];
            })
            ->values();

        return response()->json([
            'columns' => $columns,
            'mentees' => $mentees,
        ]);
    }

    /**
     * Monthly-performance grid data for a chosen year + month window. Sales per
     * month are the SUM of the host's effective daily sales (auto live-session
     * GMV, with any PIC override), so the month cell is a read-only rollup of the
     * daily strip; Attitude + notes remain the PIC's manual monthly entry. Each
     * mentee also carries their effective PIC (per-host mentor override, else the
     * program leader) for grouping and inline reassignment.
     *
     * @return array<string, mixed>
     */
    /**
     * Cross-program Monthly Performance overview. Aggregates the very same monthly
     * grid that lives on each program's Performance tab, but for EVERY active
     * program at once, so the PIC can monitor (and edit inline) without drilling
     * into each program. Only 'active' programs are shown — drafts, paused, and
     * completed are excluded. Every program carries the identical `performance`
     * payload the single-program grid consumes, so the React side reuses the
     * MonthlyPerformanceTab component verbatim, one section per program.
     */
    public function overview(Request $request): Response
    {
        $programs = LiveHostMentoringProgram::query()
            ->where('status', 'active')
            ->archived(false)
            ->with('leader:id,name')
            ->withCount([
                'mentees as active_mentees_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->orderBy('title')
            ->get();

        $window = $this->resolveMonthWindow($request);

        $payload = $programs->map(fn (LiveHostMentoringProgram $program) => [
            'program' => [
                'id' => $program->id,
                'title' => $program->title,
                'slug' => $program->slug,
                'status' => $program->status,
                'active_mentees_count' => (int) ($program->active_mentees_count ?? 0),
                'leader' => $program->leader ? [
                    'id' => $program->leader->id,
                    'name' => $program->leader->name,
                    'initials' => self::initials($program->leader->name),
                ] : null,
            ],
            'performance' => $this->performanceData($program, $request),
        ])->values();

        return Inertia::render('mentoring/Overview', [
            'programs' => $payload,
            'window' => [
                'year' => $window['year'],
                'range' => ['from' => $window['from'], 'to' => $window['to']],
                'months' => $window['months'],
                'years' => $this->overviewYears($programs, $window['year']),
            ],
        ]);
    }

    /**
     * Selectable years for the overview month filter: from the earliest active
     * program's start year (or a year before now) through the current year,
     * always including the selected one.
     *
     * @param  Collection<int, LiveHostMentoringProgram>  $programs
     * @return array<int, int>
     */
    private function overviewYears(Collection $programs, int $selected): array
    {
        $current = (int) now()->format('Y');
        $starts = $programs
            ->map(fn (LiveHostMentoringProgram $p) => $p->starts_at ? (int) $p->starts_at->format('Y') : $current)
            ->push($current - 1)
            ->push($selected);

        return range((int) $starts->min(), max($current, $selected));
    }

    /**
     * The chosen year + month window shared by the performance grid, derived from
     * the perf_year / perf_from / perf_to query params (defaulting to the last six
     * months up to the current month). Returns both the month descriptors the grid
     * renders and the year/month periods the sales resolver aggregates over.
     *
     * @return array{year: int, from: int, to: int, months: Collection<int, array<string, mixed>>, periods: array<int, array{year: int, month: int}>}
     */
    private function resolveMonthWindow(Request $request): array
    {
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        $year = $request->integer('perf_year') ?: $currentYear;
        $defaultTo = $year === $currentYear ? $currentMonth : 12;
        $to = $request->integer('perf_to') ?: $defaultTo;
        $from = $request->integer('perf_from') ?: max(1, $to - 5);

        $from = max(1, min(12, $from));
        $to = max(1, min(12, $to));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $months = collect(range($from, $to))
            ->map(fn ($m) => [
                'value' => sprintf('%04d-%02d', $year, $m),
                'year' => $year,
                'month' => (int) $m,
                'label' => CarbonImmutable::create($year, $m, 1)->format('M Y'),
            ])->values();

        return [
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'months' => $months,
            'periods' => $months->map(fn ($mo) => ['year' => $mo['year'], 'month' => $mo['month']])->all(),
        ];
    }

    private function performanceData(LiveHostMentoringProgram $program, Request $request): array
    {
        $window = $this->resolveMonthWindow($request);
        $year = $window['year'];
        $from = $window['from'];
        $to = $window['to'];
        $months = $window['months'];
        $periods = $window['periods'];

        $mentees = $program->mentees()
            ->whereIn('status', ['active', 'graduated'])
            ->with([
                'menteeUser:id,name',
                'mentor:id,name',
                'level:id,name,color,monthly_sales_target',
                'monthlyScores',
            ])
            ->withCount('disciplinaryRecords')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->get();

        $resolver = app(MenteeDailySalesResolver::class);
        $salesTotals = $resolver->monthlyTotals($mentees, $periods);
        $liveCounts = $resolver->monthlyLiveCounts($mentees, $periods); // [menteeId]['YYYY-MM'] => ended sessions
        $videoCounts = $this->monthlyVideoCounts($mentees, $year, $from, $to); // [menteeId]['YYYY-MM'] => videos logged

        $leader = $program->leader;
        $leaderData = $leader ? [
            'id' => $leader->id,
            'name' => $leader->name,
            'initials' => self::initials($leader->name),
        ] : null;

        $menteesOut = $mentees->map(function (LiveHostMentee $m) use ($salesTotals, $liveCounts, $videoCounts, $months, $leaderData) {
            $scoreByKey = $m->monthlyScores->keyBy(fn ($s) => $s->periodKey());

            $scores = [];
            foreach ($months as $mo) {
                $key = $mo['value'];
                $ms = $scoreByKey->get($key);
                $scores[$key] = [
                    'attitude' => $ms?->attitude_score,
                    'sales' => $salesTotals[$m->id][$key] ?? null,
                    'notes' => $ms?->notes,
                    'video_actual' => (int) ($videoCounts[$m->id][$key] ?? 0),
                    'live_actual' => (int) ($liveCounts[$m->id][$key] ?? 0),
                    'video_target' => $ms?->video_target,
                    'live_target' => $ms?->live_target,
                ];
            }

            $pic = $m->mentor
                ? ['id' => $m->mentor->id, 'name' => $m->mentor->name, 'initials' => self::initials($m->mentor->name), 'is_override' => true]
                : ($leaderData ? array_merge($leaderData, ['is_override' => false]) : null);

            return [
                'id' => $m->id,
                'name' => $m->menteeUser?->name,
                'status' => $m->status,
                'level' => $m->level ? ['id' => $m->level->id, 'name' => $m->level->name, 'color' => $m->level->color] : null,
                'level_id' => $m->level_id,
                'sales_target' => $m->level?->monthly_sales_target,
                'mentor_user_id' => $m->mentor_user_id,
                'pic' => $pic,
                'disciplinary_count' => (int) ($m->disciplinary_records_count ?? 0),
                'scores' => $scores,
            ];
        })->values();

        return [
            'year' => $year,
            'range' => ['from' => $from, 'to' => $to],
            'years' => $this->performanceYears($program, $year),
            'months' => $months,
            'mentees' => $menteesOut,
            'pics' => $this->assignablePicsCache ??= app(MenteeBoardPresenter::class)->assignableMentors(),
            'levels' => $this->activeLevelsCache ??= LiveHostMentoringLevel::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->get(['id', 'name', 'color'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])
                ->values(),
            'leader' => $leaderData,
        ];
    }

    /**
     * Video-log counts per mentee per month — the actual behind the monthly Video
     * KPI. Videos are logged by the host (title + optional link); here we count how
     * many landed in each month of the window. Months with no videos are absent
     * from the map (the caller defaults them to 0).
     *
     * @param  Collection<int, LiveHostMentee>  $mentees
     * @return array<int, array<string, int>> [menteeId][ 'YYYY-MM' ] => video count
     */
    private function monthlyVideoCounts(Collection $mentees, int $year, int $from, int $to): array
    {
        if ($mentees->isEmpty()) {
            return [];
        }

        $start = CarbonImmutable::create($year, $from, 1)->startOfMonth();
        $end = CarbonImmutable::create($year, $to, 1)->endOfMonth();

        return LiveHostMenteeDailyVideo::query()
            ->whereIn('mentee_id', $mentees->pluck('id'))
            ->whereBetween('video_date', [$start->toDateString(), $end->toDateString()])
            ->get(['mentee_id', 'video_date'])
            ->groupBy('mentee_id')
            ->map(fn (Collection $rows) => $rows
                ->countBy(fn (LiveHostMenteeDailyVideo $v) => $v->video_date->format('Y-m'))
                ->all())
            ->all();
    }

    /**
     * Selectable years for the month filter: from the program's start year (or a
     * year before now) through the current year, always including the selected one.
     *
     * @return array<int, int>
     */
    private function performanceYears(LiveHostMentoringProgram $program, int $selected): array
    {
        $current = (int) now()->format('Y');
        $start = $program->starts_at ? (int) $program->starts_at->format('Y') : $current;
        $min = min($start, $current - 1, $selected);
        $max = max($current, $selected);

        return range($min, $max);
    }

    public function update(MentoringProgramRequest $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        $data = $request->validated();
        unset($data['status']); // status goes through the lifecycle endpoints
        $data['slug'] = ($data['slug'] ?? '') ?: $this->uniqueSlugFor($data['title'], $program->id);

        $program->update($data);

        return redirect()
            ->route('livehost.mentoring.programs.edit', $program)
            ->with('success', 'Program updated.');
    }

    public function activate(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        if (! in_array($program->status, ['draft', 'paused'], true)) {
            abort(422, 'Only draft or paused programs can be activated.');
        }

        if (! $program->stages()->where('is_final', true)->exists()) {
            abort(422, 'A program needs a final stage before it can be activated.');
        }

        $program->update(['status' => 'active']);

        return back()->with('success', 'Program activated.');
    }

    public function pause(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        if ($program->status !== 'active') {
            abort(422, 'Only active programs can be paused.');
        }

        $program->update(['status' => 'paused']);

        return back()->with('success', 'Program paused.');
    }

    public function complete(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        if (! in_array($program->status, ['active', 'paused'], true)) {
            abort(422, 'Only active or paused programs can be completed.');
        }

        $program->update(['status' => 'completed']);

        return back()->with('success', 'Program marked as completed.');
    }

    public function destroy(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        if ($program->mentees()->count() > 0) {
            abort(422, 'Cannot delete a program that already has mentees.');
        }

        $title = $program->title;
        $program->delete();

        return redirect()
            ->route('livehost.mentoring.programs.index')
            ->with('success', "Program \"{$title}\" deleted.");
    }

    /**
     * Archive a program: it drops out of the desk list and its mentees'
     * performance is hidden in the Live Host Pocket app. Nothing is deleted —
     * archiving is fully reversible via restore().
     */
    public function archive(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        if ($program->archived_at === null) {
            $program->update(['archived_at' => now()]);
        }

        return redirect()
            ->route('livehost.mentoring.programs.index')
            ->with('success', "Program \"{$program->title}\" archived.");
    }

    /**
     * Restore a previously archived program back into the active list.
     */
    public function restore(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        if ($program->archived_at !== null) {
            $program->update(['archived_at' => null]);
        }

        return redirect()
            ->route('livehost.mentoring.programs.index', ['view' => 'archived'])
            ->with('success', "Program \"{$program->title}\" restored.");
    }

    /**
     * Clone a program's template (stages + checklist) into a fresh draft copy.
     * Instance data (mentees, activities, leader runtime, dates) is not copied.
     */
    public function duplicate(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        $copy = DB::transaction(function () use ($program, $request) {
            $title = $program->title.' (Copy)';

            $new = LiveHostMentoringProgram::create([
                'title' => $title,
                'slug' => $this->uniqueSlugFor($title),
                'description' => $program->description,
                'status' => 'draft',
                'leader_user_id' => $program->leader_user_id,
                'starts_at' => null,
                'ends_at' => null,
                'created_by' => $request->user()->id,
                'checklist_template' => $program->checklist_template,
            ]);

            // Creating a program auto-seeds the default stages; replace them with
            // the source program's actual stages so customisations carry over.
            $new->stages()->delete();

            foreach ($program->stages()->orderBy('position')->get() as $stage) {
                $new->stages()->create([
                    'position' => $stage->position,
                    'name' => $stage->name,
                    'description' => $stage->description,
                    'is_final' => $stage->is_final,
                ]);
            }

            return $new;
        });

        return redirect()
            ->route('livehost.mentoring.programs.edit', $copy)
            ->with('success', "Program duplicated as \"{$copy->title}\". Review the copy, then activate when ready.");
    }

    /**
     * Users who can lead a program: live hosts (the "top hosts") plus Live Host
     * Desk staff (the desk admin and assistants). Top-host-eligible graduates are
     * surfaced first so they're easy to promote into a mentor; the frontend groups
     * staff and hosts under separate headings using the `is_staff` / `role_label`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function assignableLeaders(): Collection
    {
        $roleLabels = [
            'admin_livehost' => 'Admin',
            'livehost_assistant' => 'Assistant',
            'live_host' => 'Live Host',
        ];

        return User::query()
            ->whereIn('role', array_keys($roleLabels))
            ->orderByDesc('is_top_host_eligible')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_top_host_eligible'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => self::initials($u->name),
                'role' => $u->role,
                'role_label' => $roleLabels[$u->role] ?? 'Live Host',
                'is_staff' => $u->role !== 'live_host',
                'is_top_host_eligible' => (bool) $u->is_top_host_eligible,
            ])
            ->values();
    }

    private function uniqueSlugFor(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'program';
        $slug = $base;
        $i = 2;
        while (
            LiveHostMentoringProgram::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private static function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
