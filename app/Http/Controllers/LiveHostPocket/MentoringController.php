<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentoringLevel;
use Illuminate\Http\Request;
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

        $mentee = $user->activeMenteeEnrollment()
            ->with([
                'program.stages' => fn ($q) => $q->orderBy('position'),
                'program.leader:id,name',
                'mentor:id,name',
                'currentStage',
                'level',
                'checklistItems' => fn ($q) => $q->orderBy('position'),
                'activities' => fn ($q) => $q->latest('occurred_at')->limit(10),
            ])
            ->first();

        if ($mentee === null) {
            return Inertia::render('MyPath', ['enrollment' => null]);
        }

        $currentPosition = $mentee->currentStage?->position ?? 0;
        $stages = $mentee->program->stages->map(fn ($s) => [
            'name' => $s->name,
            'position' => (int) $s->position,
            'is_final' => (bool) $s->is_final,
            'state' => $s->position < $currentPosition ? 'done' : ($s->position === $currentPosition ? 'current' : 'upcoming'),
        ])->values();

        $checklist = $mentee->checklistItems;
        $done = $checklist->where('status', 'done')->count();
        $total = $checklist->count();

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
                'ladder' => $ladder,
                'checklist' => [
                    'done' => $done,
                    'total' => $total,
                    'pct' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
                    'items' => $checklist->map(fn ($c) => [
                        'title' => $c->title,
                        'is_required' => (bool) $c->is_required,
                        'status' => $c->status,
                    ])->values(),
                ],
                'activities' => $mentee->activities->map(fn ($a) => [
                    'type' => $a->type,
                    'title' => $a->title,
                    'occurred_at_human' => $a->occurred_at?->diffForHumans(),
                ])->values(),
            ],
        ]);
    }
}
