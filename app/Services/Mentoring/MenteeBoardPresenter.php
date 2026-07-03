<?php

namespace App\Services\Mentoring;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveHostMentoringStage;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the kanban "mentee board" payload for a mentoring program. Returns
 * every mentee (all statuses) so the board can filter by status client-side,
 * which lets the same board live both on its own page and embedded in the
 * program editor as a tab without per-status server round-trips.
 */
class MenteeBoardPresenter
{
    /**
     * @return array{stages: Collection<int, array<string, mixed>>, mentees: Collection<int, array<string, mixed>>, counts: array<string, int>, assignableMentors: Collection<int, array<string, mixed>>, enrollableHosts: Collection<int, array<string, mixed>>}
     */
    public function forProgram(LiveHostMentoringProgram $program): array
    {
        $mentees = LiveHostMentee::query()
            ->where('program_id', $program->id)
            ->with(['currentStageRow.assignee', 'menteeUser', 'level'])
            ->orderByDesc('enrolled_at')
            ->get();

        return [
            'stages' => $program->stages()->orderBy('position')->get(['id', 'name', 'position', 'is_final'])
                ->map(fn (LiveHostMentoringStage $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'position' => (int) $s->position,
                    'is_final' => (bool) $s->is_final,
                ])->values(),
            'mentees' => $mentees->map(fn (LiveHostMentee $m) => $this->cardData($m))->values(),
            'counts' => [
                'active' => $mentees->where('status', 'active')->count(),
                'graduated' => $mentees->where('status', 'graduated')->count(),
                'dropped' => $mentees->where('status', 'dropped')->count(),
            ],
            'assignableMentors' => $this->assignableMentors(),
            'enrollableHosts' => $this->enrollableHosts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cardData(LiveHostMentee $m): array
    {
        $row = $m->currentStageRow;

        return [
            'id' => $m->id,
            'mentee_number' => $m->mentee_number,
            'full_name' => $m->menteeUser?->name,
            'email' => $m->menteeUser?->email,
            'phone' => $m->menteeUser?->phone,
            'current_stage_id' => $m->current_stage_id,
            'status' => $m->status,
            'mentor_user_id' => $m->mentor_user_id,
            'level' => $m->level ? [
                'id' => $m->level->id,
                'name' => $m->level->name,
                'color' => $m->level->color,
            ] : null,
            'enrolled_at' => $m->enrolled_at?->toIso8601String(),
            'enrolled_at_human' => $m->enrolled_at?->diffForHumans(),
            'assignment' => $row ? [
                'assignee' => $row->assignee ? [
                    'id' => $row->assignee->id,
                    'name' => $row->assignee->name,
                    'initials' => self::initials($row->assignee->name),
                ] : null,
                'due_at' => $row->due_at?->toIso8601String(),
                'is_overdue' => $row->is_overdue,
                'stage_notes' => $row->stage_notes,
            ] : null,
        ];
    }

    /**
     * Live hosts and live host assistants who can mentor / be a PIC — live hosts
     * first (top-host-eligible surfaced first within), then assistants. Each entry
     * carries its role so pickers can group assistants apart from full mentors.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function assignableMentors(): Collection
    {
        return User::query()
            ->whereIn('role', ['live_host', 'livehost_assistant'])
            ->orderByRaw("CASE WHEN role = 'live_host' THEN 0 ELSE 1 END")
            ->orderByDesc('is_top_host_eligible')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'is_top_host_eligible'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => self::initials($u->name),
                'is_top_host_eligible' => (bool) $u->is_top_host_eligible,
                'is_assistant' => $u->role === 'livehost_assistant',
            ])
            ->values();
    }

    /**
     * Live hosts and live host assistants (part-time hosts) not currently an
     * active mentee anywhere — enforces the "one active program at a time" rule
     * at the enrollment picker. Each entry carries its role so the picker can
     * group full hosts apart from assistants.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function enrollableHosts(): Collection
    {
        $activeMenteeUserIds = LiveHostMentee::query()
            ->where('status', 'active')
            ->pluck('mentee_user_id')
            ->all();

        return User::query()
            ->whereIn('role', ['live_host', 'livehost_assistant'])
            ->whereNotIn('id', $activeMenteeUserIds)
            ->orderByRaw("CASE WHEN role = 'live_host' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'is_assistant' => $u->role === 'livehost_assistant',
                'initials' => self::initials($u->name),
            ])
            ->values();
    }

    public static function initials(?string $name): string
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
