<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\Mentoring\MentoringProgramRequest;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\MenteeBoardPresenter;
use App\Services\Mentoring\MentorActivityIndicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MentoringProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $paginator = LiveHostMentoringProgram::query()
            ->with('leader:id,name')
            ->withCount([
                'mentees',
                'mentees as active_mentees_count' => fn ($q) => $q->where('status', 'active'),
                'mentees as graduated_mentees_count' => fn ($q) => $q->where('status', 'graduated'),
            ])
            ->latest()
            ->paginate(20);

        $indicators = app(MentorActivityIndicator::class)
            ->forLeaders($paginator->getCollection()->pluck('leader_user_id')->all());

        $programs = $paginator->through(fn (LiveHostMentoringProgram $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'status' => $p->status,
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
            'performance' => $this->performanceData($program),
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
     * Monthly-performance grid data: every month of the current calendar year
     * (January → December, ascending) and every active/graduated mentee with
     * their recorded scores keyed by 'YYYY-MM'.
     *
     * @return array<string, mixed>
     */
    private function performanceData(LiveHostMentoringProgram $program): array
    {
        $startOfYear = now()->startOfYear();
        $months = collect(range(0, 11))
            ->map(fn ($i) => $startOfYear->copy()->addMonths($i))
            ->map(fn ($d) => [
                'value' => $d->format('Y-m'),
                'year' => (int) $d->format('Y'),
                'month' => (int) $d->format('n'),
                'label' => $d->format('M Y'),
            ])->values();

        $mentees = $program->mentees()
            ->whereIn('status', ['active', 'graduated'])
            ->with(['menteeUser:id,name', 'level:id,name,color,monthly_sales_target', 'monthlyScores'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->get()
            ->map(fn (LiveHostMentee $m) => [
                'id' => $m->id,
                'name' => $m->menteeUser?->name,
                'status' => $m->status,
                'level' => $m->level ? ['name' => $m->level->name, 'color' => $m->level->color] : null,
                // The mentee's monthly sales target comes from their level; the Sales
                // KPI is actual ÷ target, and feeds the computed Overall on the client.
                'sales_target' => $m->level?->monthly_sales_target,
                'scores' => $m->monthlyScores->mapWithKeys(fn ($s) => [
                    $s->periodKey() => [
                        'attitude' => $s->attitude_score,
                        'sales' => $s->sales_quantity !== null ? (float) $s->sales_quantity : null,
                        'notes' => $s->notes,
                    ],
                ]),
            ])->values();

        return ['months' => $months, 'mentees' => $mentees];
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
     * Live hosts who can lead a program (the "top hosts"). Top-host-eligible
     * graduates are surfaced first so they're easy to promote into a mentor.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function assignableLeaders(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderByDesc('is_top_host_eligible')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_top_host_eligible'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => self::initials($u->name),
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
