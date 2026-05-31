<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeChecklistItem;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringStage;
use App\Services\Mentoring\LevelSuggester;
use App\Services\Mentoring\MenteeKpiReport;
use App\Services\Mentoring\MenteeStageTransition;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — "My Mentees".
 *
 * A top host's cockpit for the mentees they are responsible for (their program
 * leadership or a per-mentee override). Every action is scoped to mentees the
 * authenticated host actually mentors; the PIC desk remains the admin surface.
 */
class MentorController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $mentees = LiveHostMentee::query()
            ->mentoredBy($userId)
            ->whereIn('status', ['active', 'graduated'])
            ->with(['menteeUser:id,name', 'currentStage:id,name,position,is_final', 'level:id,name,color', 'program:id,title'])
            ->withCount([
                'checklistItems',
                'checklistItems as checklist_done_count' => fn ($q) => $q->where('status', 'done'),
            ])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->get()
            ->map(fn (LiveHostMentee $m) => [
                'id' => $m->id,
                'name' => $m->menteeUser?->name,
                'mentee_number' => $m->mentee_number,
                'status' => $m->status,
                'program' => $m->program?->title,
                'current_stage' => $m->currentStage?->name,
                'level' => $m->level ? ['name' => $m->level->name, 'color' => $m->level->color] : null,
                'checklist_total' => (int) ($m->checklist_items_count ?? 0),
                'checklist_done' => (int) ($m->checklist_done_count ?? 0),
            ])
            ->values();

        return Inertia::render('Mentees', [
            'mentees' => $mentees,
        ]);
    }

    public function show(Request $request, LiveHostMentee $mentee): Response
    {
        $this->authorizeMentor($request, $mentee);

        $mentee->load([
            'menteeUser:id,name,email,phone',
            'program:id,title',
            'program.stages' => fn ($q) => $q->orderBy('position'),
            'currentStage',
            'level',
            'checklistItems' => fn ($q) => $q->orderBy('position'),
            'activities' => fn ($q) => $q->latest('occurred_at')->limit(15),
        ]);

        $to = CarbonImmutable::now();
        $from = $to->subDays(30);
        $kpis = app(MenteeKpiReport::class)->forUser($mentee->mentee_user_id, $from, $to);
        $suggested = app(LevelSuggester::class)->suggest($kpis);

        return Inertia::render('MenteeCoach', [
            'mentee' => [
                'id' => $mentee->id,
                'name' => $mentee->menteeUser?->name,
                'mentee_number' => $mentee->mentee_number,
                'phone' => $mentee->menteeUser?->phone,
                'status' => $mentee->status,
                'program' => $mentee->program?->title,
                'current_stage_id' => $mentee->current_stage_id,
                'current_stage' => $mentee->currentStage ? [
                    'id' => $mentee->currentStage->id,
                    'name' => $mentee->currentStage->name,
                    'position' => (int) $mentee->currentStage->position,
                    'is_final' => (bool) $mentee->currentStage->is_final,
                ] : null,
                'level' => $mentee->level ? ['id' => $mentee->level->id, 'name' => $mentee->level->name, 'color' => $mentee->level->color] : null,
            ],
            'stages' => $mentee->program->stages->map(fn (LiveHostMentoringStage $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'position' => (int) $s->position,
                'is_final' => (bool) $s->is_final,
            ])->values(),
            'kpis' => $kpis,
            'suggestedLevel' => $suggested ? ['id' => $suggested->id, 'name' => $suggested->name, 'color' => $suggested->color] : null,
            'levels' => LiveHostMentoringLevel::query()->active()->orderBy('position')->get()
                ->map(fn (LiveHostMentoringLevel $l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color, 'is_top' => (bool) $l->is_top])
                ->values(),
            'checklist' => $mentee->checklistItems->map(fn (LiveHostMenteeChecklistItem $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'is_required' => (bool) $c->is_required,
                'status' => $c->status,
            ])->values(),
            'activities' => $mentee->activities->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'title' => $a->title,
                'occurred_at_human' => $a->occurred_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function logActivity(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $this->authorizeMentor($request, $mentee);

        $data = $request->validate([
            'type' => ['required', 'in:coaching,meeting,training,check_in,other'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $mentee->activities()->create([
            'program_id' => $mentee->program_id,
            'leader_user_id' => $request->user()->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'occurred_at' => $this->normalizeDate($data['occurred_at'] ?? null),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Activity logged.');
    }

    public function toggleChecklistItem(Request $request, LiveHostMentee $mentee, LiveHostMenteeChecklistItem $item): RedirectResponse
    {
        $this->authorizeMentor($request, $mentee);
        abort_unless($item->mentee_id === $mentee->id, 404);

        $done = $item->status !== 'done';
        $item->update([
            'status' => $done ? 'done' : 'pending',
            'completed_at' => $done ? now() : null,
            'completed_by' => $done ? $request->user()->id : null,
        ]);

        return back()->with('success', $done ? 'Task completed.' : 'Task reopened.');
    }

    public function assignLevel(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $this->authorizeMentor($request, $mentee);

        $data = $request->validate([
            'level_id' => ['nullable', 'integer', 'exists:live_host_mentoring_levels,id'],
            'source' => ['nullable', 'in:manual,auto'],
        ]);

        $levelId = $data['level_id'] ?? null;
        $source = $data['source'] ?? 'manual';
        $level = $levelId ? LiveHostMentoringLevel::find($levelId) : null;

        DB::transaction(function () use ($mentee, $levelId, $source, $level, $request): void {
            $mentee->update([
                'level_id' => $levelId,
                'level_source' => $levelId ? $source : null,
                'level_assigned_at' => $levelId ? now() : null,
                'level_assigned_by' => $levelId ? $request->user()->id : null,
            ]);

            $mentee->history()->create([
                'from_stage_id' => $mentee->current_stage_id,
                'to_stage_id' => $mentee->current_stage_id,
                'action' => 'leveled',
                'notes' => $level ? "Level set to {$level->name}".($source === 'auto' ? ' (auto-suggested)' : '') : 'Level cleared',
                'changed_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', $level ? "Level set to {$level->name}." : 'Level cleared.');
    }

    public function moveStage(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $this->authorizeMentor($request, $mentee);
        abort_if($mentee->status !== 'active', HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Mentee is not active.');

        $data = $request->validate([
            'to_stage_id' => ['required', 'integer', 'exists:live_host_mentoring_stages,id'],
        ]);

        $toStage = LiveHostMentoringStage::findOrFail($data['to_stage_id']);
        abort_unless($toStage->program_id === $mentee->program_id, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Stage does not belong to this program.');

        $fromStageId = $mentee->current_stage_id;
        $mentee->loadMissing('currentStage');
        $action = ($mentee->currentStage && $toStage->position < $mentee->currentStage->position) ? 'reverted' : 'advanced';

        DB::transaction(function () use ($mentee, $toStage, $fromStageId, $action, $request) {
            app(MenteeStageTransition::class)->transition($mentee, $toStage);
            $mentee->history()->create([
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $toStage->id,
                'action' => $action,
                'changed_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', "Moved to \"{$toStage->name}\".");
    }

    public function graduate(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $this->authorizeMentor($request, $mentee);
        abort_if($mentee->status !== 'active', HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Mentee is not active.');

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
                'changed_by' => $request->user()->id,
            ]);

            app(MenteeStageTransition::class)->closeOpenRow($mentee);

            $mentee->update(['status' => 'graduated', 'graduated_at' => now()]);
            $mentee->menteeUser?->forceFill(['is_top_host_eligible' => true])->save();
        });

        return back()->with('success', "{$mentee->menteeUser?->name} graduated. Congratulations, mentor!");
    }

    private function authorizeMentor(Request $request, LiveHostMentee $mentee): void
    {
        $mentee->loadMissing('program');
        abort_unless($mentee->isMentoredBy($request->user()->id), HttpResponse::HTTP_FORBIDDEN);
    }

    private function normalizeDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return now()->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return now()->format('Y-m-d H:i:s');
        }
    }
}
