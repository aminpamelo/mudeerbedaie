<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\Mentoring\AssignMenteeLevelRequest;
use App\Http\Requests\LiveHost\Mentoring\EnrollMenteeRequest;
use App\Http\Requests\LiveHost\Mentoring\UpdateMenteeCurrentStageRequest;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeChecklistItem;
use App\Models\LiveHostMenteeStage;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveHostMentoringStage;
use App\Services\Mentoring\LevelSuggester;
use App\Services\Mentoring\MenteeBoardPresenter;
use App\Services\Mentoring\MenteeKpiReport;
use App\Services\Mentoring\MenteeStageTransition;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MentoringMenteeController extends Controller
{
    public function index(Request $request): Response
    {
        $programId = $request->integer('program') ?: null;

        $program = $programId
            ? LiveHostMentoringProgram::find($programId)
            : LiveHostMentoringProgram::where('status', 'active')->oldest('created_at')->first()
                ?? LiveHostMentoringProgram::latest('created_at')->first();

        $statusTab = $request->input('status', 'active');
        if (! in_array($statusTab, ['active', 'graduated', 'dropped'], true)) {
            $statusTab = 'active';
        }

        $program?->loadMissing('leader:id,name');

        $board = $program
            ? app(MenteeBoardPresenter::class)->forProgram($program)
            : [
                'stages' => collect(),
                'mentees' => collect(),
                'counts' => ['active' => 0, 'graduated' => 0, 'dropped' => 0],
                'assignableMentors' => collect(),
                'enrollableHosts' => collect(),
            ];

        return Inertia::render('mentoring/mentees/Index', [
            'program' => $program ? [
                'id' => $program->id,
                'title' => $program->title,
                'slug' => $program->slug,
                'status' => $program->status,
                'description' => $program->description,
                'leader_user_id' => $program->leader_user_id,
                'leader' => $program->leader ? [
                    'id' => $program->leader->id,
                    'name' => $program->leader->name,
                    'initials' => self::initials($program->leader->name),
                ] : null,
                'starts_at' => $program->starts_at?->toIso8601String(),
                'ends_at' => $program->ends_at?->toIso8601String(),
            ] : null,
            'counts' => $board['counts'],
            'stages' => collect($board['stages'])->values(),
            'mentees' => collect($board['mentees'])->values(),
            'programs' => LiveHostMentoringProgram::orderByDesc('created_at')
                ->get(['id', 'title', 'status'])
                ->map(fn (LiveHostMentoringProgram $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'status' => $p->status,
                ])
                ->values(),
            'assignableMentors' => $board['assignableMentors'],
            'enrollableHosts' => $board['enrollableHosts'],
            'filters' => [
                'program' => $program?->id,
                'status' => $statusTab,
            ],
        ]);
    }

    public function show(Request $request, LiveHostMentee $mentee): Response
    {
        $mentee->load([
            'program.stages' => fn ($q) => $q->orderBy('position'),
            'program.leader:id,name',
            'menteeUser:id,name,email,phone,avatar_path',
            'mentor:id,name',
            'currentStage',
            'level',
            'history' => fn ($q) => $q->latest(),
            'history.fromStage',
            'history.toStage',
            'history.changedByUser',
        ]);

        // KPI snapshot over a 30-day rolling window + the auto level suggestion.
        $to = CarbonImmutable::now();
        $from = $to->subDays(30);
        $kpis = app(MenteeKpiReport::class)->forUser($mentee->mentee_user_id, $from, $to);
        $suggested = app(LevelSuggester::class)->suggest($kpis);

        return Inertia::render('mentoring/mentees/Show', [
            'mentee' => [
                'id' => $mentee->id,
                'mentee_number' => $mentee->mentee_number,
                'full_name' => $mentee->menteeUser?->name,
                'email' => $mentee->menteeUser?->email,
                'phone' => $mentee->menteeUser?->phone,
                'mentee_user_id' => $mentee->mentee_user_id,
                'mentor_user_id' => $mentee->mentor_user_id,
                'mentor' => $mentee->mentor ? [
                    'id' => $mentee->mentor->id,
                    'name' => $mentee->mentor->name,
                    'initials' => self::initials($mentee->mentor->name),
                ] : null,
                'status' => $mentee->status,
                'notes' => $mentee->notes,
                'enrolled_at' => $mentee->enrolled_at?->toIso8601String(),
                'enrolled_at_human' => $mentee->enrolled_at?->diffForHumans(),
                'graduated_at' => $mentee->graduated_at?->toIso8601String(),
                'current_stage_id' => $mentee->current_stage_id,
                'current_stage' => $mentee->currentStage ? [
                    'id' => $mentee->currentStage->id,
                    'name' => $mentee->currentStage->name,
                    'position' => (int) $mentee->currentStage->position,
                    'is_final' => (bool) $mentee->currentStage->is_final,
                ] : null,
                'level' => $mentee->level ? [
                    'id' => $mentee->level->id,
                    'name' => $mentee->level->name,
                    'color' => $mentee->level->color,
                ] : null,
                'program' => [
                    'id' => $mentee->program->id,
                    'title' => $mentee->program->title,
                    'status' => $mentee->program->status,
                    'leader' => $mentee->program->leader ? [
                        'id' => $mentee->program->leader->id,
                        'name' => $mentee->program->leader->name,
                    ] : null,
                ],
            ],
            'stages' => $mentee->program->stages->map(fn (LiveHostMentoringStage $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'position' => (int) $s->position,
                'is_final' => (bool) $s->is_final,
            ])->values(),
            'history' => $mentee->history->map(fn ($h) => [
                'id' => $h->id,
                'action' => $h->action,
                'notes' => $h->notes,
                'from_stage' => $h->fromStage ? ['id' => $h->fromStage->id, 'name' => $h->fromStage->name] : null,
                'to_stage' => $h->toStage ? ['id' => $h->toStage->id, 'name' => $h->toStage->name] : null,
                'changed_by' => $h->changedByUser ? ['id' => $h->changedByUser->id, 'name' => $h->changedByUser->name] : null,
                'created_at' => $h->created_at?->toIso8601String(),
                'created_at_human' => $h->created_at?->diffForHumans(),
            ])->values(),
            'kpis' => $kpis,
            'suggestedLevel' => $suggested ? [
                'id' => $suggested->id,
                'name' => $suggested->name,
                'color' => $suggested->color,
            ] : null,
            'levels' => LiveHostMentoringLevel::query()->active()->orderBy('position')->get()
                ->map(fn (LiveHostMentoringLevel $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                    'is_top' => (bool) $l->is_top,
                ])->values(),
            'activities' => $mentee->activities()
                ->with('creator:id,name')
                ->limit(30)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'title' => $a->title,
                    'notes' => $a->notes,
                    'occurred_at' => $a->occurred_at?->toIso8601String(),
                    'occurred_at_human' => $a->occurred_at?->diffForHumans(),
                    'created_by' => $a->creator?->name,
                ])->values(),
            'checklist' => $mentee->checklistItems()->orderBy('position')->get()
                ->map(fn (LiveHostMenteeChecklistItem $c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'description' => $c->description,
                    'is_required' => (bool) $c->is_required,
                    'status' => $c->status,
                    'completed_at' => $c->completed_at?->toIso8601String(),
                    'completed_at_human' => $c->completed_at?->diffForHumans(),
                ])->values(),
            'assignableMentors' => app(MenteeBoardPresenter::class)->assignableMentors(),
        ]);
    }

    /**
     * Rich detail for the mentee-hub modal: activity log, checklist, stage
     * history and a 30-day KPI snapshot — loaded on demand so the kanban board
     * payload stays lightweight.
     */
    public function detail(LiveHostMentee $mentee): JsonResponse
    {
        $mentee->load([
            'history' => fn ($q) => $q->latest(),
            'history.fromStage',
            'history.toStage',
            'history.changedByUser',
        ]);

        $to = CarbonImmutable::now();
        $from = $to->subDays(30);

        return response()->json([
            'program_id' => $mentee->program_id,
            'current_stage_id' => $mentee->current_stage_id,
            'kpis' => app(MenteeKpiReport::class)->forUser($mentee->mentee_user_id, $from, $to),
            'history' => $mentee->history->map(fn ($h) => [
                'id' => $h->id,
                'action' => $h->action,
                'notes' => $h->notes,
                'from_stage' => $h->fromStage ? ['id' => $h->fromStage->id, 'name' => $h->fromStage->name] : null,
                'to_stage' => $h->toStage ? ['id' => $h->toStage->id, 'name' => $h->toStage->name] : null,
                'changed_by' => $h->changedByUser ? ['id' => $h->changedByUser->id, 'name' => $h->changedByUser->name] : null,
                'created_at_human' => $h->created_at?->diffForHumans(),
            ])->values(),
            'activities' => $mentee->activities()
                ->with('creator:id,name')
                ->limit(50)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'title' => $a->title,
                    'notes' => $a->notes,
                    'occurred_at_human' => $a->occurred_at?->diffForHumans(),
                    'created_by' => $a->creator?->name,
                ])->values(),
            'checklist' => $mentee->checklistItems()->orderBy('position')->get()
                ->map(fn (LiveHostMenteeChecklistItem $c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'description' => $c->description,
                    'is_required' => (bool) $c->is_required,
                    'status' => $c->status,
                    'completed_at_human' => $c->completed_at?->diffForHumans(),
                ])->values(),
            'monthlyScores' => $mentee->monthlyScores()
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->limit(12)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'period' => CarbonImmutable::create($s->year, $s->month, 1)->format('M Y'),
                    'attitude_score' => $s->attitude_score,
                    'sales_quantity' => $s->sales_quantity,
                    'notes' => $s->notes,
                ])->values(),
        ]);
    }

    public function storeChecklistItem(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $mentee->checklistItems()->create([
            'title' => $data['title'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'position' => ((int) $mentee->checklistItems()->max('position')) + 1,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Task added.');
    }

    public function toggleChecklistItem(Request $request, LiveHostMentee $mentee, LiveHostMenteeChecklistItem $item): HttpResponse
    {
        abort_unless($item->mentee_id === $mentee->id, 404);

        $done = $item->status !== 'done';
        $item->update([
            'status' => $done ? 'done' : 'pending',
            'completed_at' => $done ? now() : null,
            'completed_by' => $done ? $request->user()?->id : null,
        ]);

        return response()->noContent();
    }

    public function destroyChecklistItem(Request $request, LiveHostMentee $mentee, LiveHostMenteeChecklistItem $item): RedirectResponse
    {
        abort_unless($item->mentee_id === $mentee->id, 404);

        $item->delete();

        return back()->with('success', 'Task removed.');
    }

    public function assignLevel(AssignMenteeLevelRequest $request, LiveHostMentee $mentee): RedirectResponse
    {
        $data = $request->validated();
        $levelId = $data['level_id'] ?? null;
        $source = $data['source'] ?? 'manual';
        $level = $levelId ? LiveHostMentoringLevel::find($levelId) : null;

        DB::transaction(function () use ($mentee, $levelId, $source, $level, $request): void {
            $mentee->update([
                'level_id' => $levelId,
                'level_source' => $levelId ? $source : null,
                'level_assigned_at' => $levelId ? now() : null,
                'level_assigned_by' => $levelId ? $request->user()?->id : null,
            ]);

            $note = $level
                ? "Level set to {$level->name}".($source === 'auto' ? ' (auto-suggested)' : '')
                : 'Level cleared';

            $mentee->history()->create([
                'from_stage_id' => $mentee->current_stage_id,
                'to_stage_id' => $mentee->current_stage_id,
                'action' => 'leveled',
                'notes' => $note,
                'changed_by' => $request->user()?->id,
            ]);
        });

        return back()->with('success', $level ? "Level set to {$level->name}." : 'Level cleared.');
    }

    public function enroll(EnrollMenteeRequest $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        $data = $request->validated();

        $alreadyActive = LiveHostMentee::query()
            ->where('mentee_user_id', $data['mentee_user_id'])
            ->where('status', 'active')
            ->exists();

        abort_if(
            $alreadyActive,
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'This host is already an active mentee in a program.'
        );

        $firstStage = $program->stages()->orderBy('position')->first();
        abort_if(
            $firstStage === null,
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'The program has no stages to enroll into.'
        );

        DB::transaction(function () use ($program, $data, $firstStage, $request): void {
            $mentee = $program->mentees()->create([
                'mentee_user_id' => $data['mentee_user_id'],
                'mentor_user_id' => $data['mentor_user_id'] ?? null,
                'mentee_number' => LiveHostMentee::generateMenteeNumber(),
                'current_stage_id' => $firstStage->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);

            app(MenteeStageTransition::class)->enterFirstStage($mentee);

            $mentee->history()->create([
                'from_stage_id' => null,
                'to_stage_id' => $firstStage->id,
                'action' => 'enrolled',
                'changed_by' => $request->user()?->id,
            ]);

            // Seed the per-mentee checklist from the program template.
            foreach (($program->checklist_template ?? []) as $i => $item) {
                if (empty($item['title'])) {
                    continue;
                }
                $mentee->checklistItems()->create([
                    'title' => $item['title'],
                    'is_required' => (bool) ($item['is_required'] ?? true),
                    'position' => $i,
                    'status' => 'pending',
                ]);
            }
        });

        return back()->with('success', 'Mentee enrolled into the program.');
    }

    public function moveStage(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        abort_if($mentee->status !== 'active', HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Mentee is not active.');

        $data = $request->validate([
            'to_stage_id' => ['required', 'integer', 'exists:live_host_mentoring_stages,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $toStage = LiveHostMentoringStage::findOrFail($data['to_stage_id']);
        abort_unless(
            $toStage->program_id === $mentee->program_id,
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Stage does not belong to this program.'
        );

        $fromStageId = $mentee->current_stage_id;
        $mentee->loadMissing('currentStage');

        $action = 'advanced';
        if ($mentee->currentStage && $toStage->position < $mentee->currentStage->position) {
            $action = 'reverted';
        }

        DB::transaction(function () use ($mentee, $toStage, $fromStageId, $data, $action, $request) {
            app(MenteeStageTransition::class)->transition($mentee, $toStage);
            $mentee->history()->create([
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $toStage->id,
                'action' => $action,
                'notes' => $data['notes'] ?? null,
                'changed_by' => $request->user()?->id,
            ]);
        });

        return back()->with('success', "Moved to stage \"{$toStage->name}\".");
    }

    public function updateCurrentStage(
        UpdateMenteeCurrentStageRequest $request,
        LiveHostMentee $mentee,
    ): HttpResponse {
        $data = $request->validated();

        $mentee->loadMissing('program');
        $mentorId = $data['mentor_user_id'] ?? null;
        $assigneeId = $mentorId ?? $mentee->program?->leader_user_id;

        DB::transaction(function () use ($mentee, $data, $mentorId, $assigneeId) {
            $mentee->update(['mentor_user_id' => $mentorId]);

            LiveHostMenteeStage::query()
                ->where('mentee_id', $mentee->id)
                ->whereNull('exited_at')
                ->update([
                    'assignee_id' => $assigneeId,
                    'due_at' => $data['due_at'] ?? null,
                    'stage_notes' => $data['stage_notes'] ?? null,
                    'updated_at' => now(),
                ]);
        });

        return response()->noContent();
    }

    public function updateNotes(Request $request, LiveHostMentee $mentee): HttpResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $mentee->update(['notes' => $data['notes'] ?? null]);

        return response()->noContent();
    }

    public function graduate(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        abort_if(
            $mentee->status !== 'active',
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Mentee is not active.'
        );

        $mentee->loadMissing('currentStage', 'menteeUser');
        abort_unless(
            optional($mentee->currentStage)->is_final,
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Move the mentee to the final stage before graduating.'
        );

        DB::transaction(function () use ($mentee, $request): void {
            $mentee->history()->create([
                'from_stage_id' => $mentee->current_stage_id,
                'to_stage_id' => null,
                'action' => 'graduated',
                'notes' => 'Graduated — marked eligible to become a top host.',
                'changed_by' => $request->user()?->id,
            ]);

            app(MenteeStageTransition::class)->closeOpenRow($mentee);

            $mentee->update([
                'status' => 'graduated',
                'graduated_at' => now(),
            ]);

            // The end goal: a graduated mentee becomes eligible to lead a program.
            $mentee->menteeUser?->forceFill(['is_top_host_eligible' => true])->save();
        });

        return back()->with('success', "{$mentee->menteeUser?->name} graduated and is now eligible to be a top host.");
    }

    public function drop(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        abort_if($mentee->status !== 'active', HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Mentee is not active.');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($mentee, $data, $request): void {
            $mentee->history()->create([
                'from_stage_id' => $mentee->current_stage_id,
                'to_stage_id' => null,
                'action' => 'dropped',
                'notes' => $data['notes'] ?? null,
                'changed_by' => $request->user()?->id,
            ]);
            app(MenteeStageTransition::class)->closeOpenRow($mentee);
            $mentee->update(['status' => 'dropped']);
        });

        return back()->with('success', 'Mentee dropped from the program.');
    }

    public function restore(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        abort_if(
            $mentee->status !== 'dropped',
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Only dropped mentees can be restored.'
        );
        abort_if(
            $mentee->current_stage_id === null,
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Mentee has no stage to restore to.'
        );

        DB::transaction(function () use ($mentee, $request): void {
            $mentee->update(['status' => 'active']);

            app(MenteeStageTransition::class)->closeOpenRow($mentee);

            LiveHostMenteeStage::create([
                'mentee_id' => $mentee->id,
                'stage_id' => $mentee->current_stage_id,
                'assignee_id' => $mentee->effectiveMentorId(),
                'entered_at' => now(),
            ]);

            $mentee->history()->create([
                'from_stage_id' => null,
                'to_stage_id' => $mentee->current_stage_id,
                'action' => 'restored',
                'changed_by' => $request->user()?->id,
            ]);
        });

        return back()->with('success', 'Mentee restored to active.');
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
