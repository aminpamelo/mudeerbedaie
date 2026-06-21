<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
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

        // Prefer the active enrollment; fall back to the most recent graduated one
        // so a host who finished the program still sees their performance history.
        $mentee = $user->activeMenteeEnrollment()->with($eagerLoad)->first()
            ?? $user->menteeEnrollments()->with($eagerLoad)
                ->where('status', 'graduated')
                ->latest('enrolled_at')
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

    /**
     * The host's monthly performance: latest score, a 6-month trend, and the
     * change vs the previous month. The Overall KPI mirrors the PIC-side
     * formula exactly — the mean of Attitude (0-100) and Sales% (sales ÷ the
     * level's monthly target, capped at 100) — so host and PIC see the same number.
     *
     * @return array{has_scores: bool, sales_target: int|null, latest: array<string, mixed>|null, trend: list<array<string, mixed>>, delta_overall: int|null}
     */
    private function performanceData(LiveHostMentee $mentee): array
    {
        $target = $mentee->level?->monthly_sales_target;

        $scores = $mentee->monthlyScores->map(function ($s) use ($target): array {
            $attitude = $s->attitude_score !== null ? max(0, min(100, (int) $s->attitude_score)) : null;
            $sales = $s->sales_quantity;
            $salesPct = ($sales !== null && $target && $target > 0)
                ? min(100, (int) round(($sales / $target) * 100))
                : null;

            $parts = array_values(array_filter([$attitude, $salesPct], fn ($v) => $v !== null));
            $overall = $parts !== [] ? (int) round(array_sum($parts) / count($parts)) : null;

            return [
                'period' => sprintf('%04d-%02d', $s->year, $s->month),
                'year' => (int) $s->year,
                'month' => (int) $s->month,
                'attitude' => $attitude,
                'sales' => $sales,
                'sales_pct' => $salesPct,
                'overall' => $overall,
            ];
        })->values();

        $latest = $scores->last();
        $previous = $scores->count() >= 2 ? $scores->slice(-2, 1)->first() : null;
        $delta = ($latest && $previous && $latest['overall'] !== null && $previous['overall'] !== null)
            ? $latest['overall'] - $previous['overall']
            : null;

        return [
            'has_scores' => $scores->isNotEmpty(),
            'sales_target' => $target !== null ? (int) $target : null,
            'latest' => $latest,
            'trend' => $scores->slice(-6)->values()->all(),
            'delta_overall' => $delta,
        ];
    }
}
