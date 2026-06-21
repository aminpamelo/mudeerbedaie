<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringLevel;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\MenteeStageTransition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LiveHostMentoringSeeder extends Seeder
{
    public function run(): void
    {
        $program = LiveHostMentoringProgram::where('status', 'active')->oldest('created_at')->first()
            ?? LiveHostMentoringProgram::latest('created_at')->first();

        if (! $program) {
            $this->command?->warn('No mentoring program found — skipping mentee seeding.');

            return;
        }

        $stages = $program->stages()->orderBy('position')->get();
        if ($stages->isEmpty()) {
            $this->command?->warn("Program \"{$program->title}\" has no stages — skipping.");

            return;
        }

        $finalStage = $stages->firstWhere('is_final', true) ?? $stages->last();
        $levels = LiveHostMentoringLevel::orderBy('position')->get();
        $leaderId = $program->leader_user_id;

        // Live hosts not already an active mentee anywhere (mirrors the enroll picker rule)
        // and not already enrolled in THIS program (program_id+mentee_user_id is unique).
        $activeMenteeUserIds = LiveHostMentee::where('status', 'active')->pluck('mentee_user_id')->all();
        $alreadyInProgramIds = LiveHostMentee::where('program_id', $program->id)->pluck('mentee_user_id')->all();
        $hosts = User::where('role', 'live_host')
            ->whereNotIn('id', $activeMenteeUserIds)
            ->whereNotIn('id', $alreadyInProgramIds)
            ->where('id', '!=', $leaderId)
            ->orderBy('name')
            ->get();

        if ($hosts->isEmpty()) {
            $this->command?->warn('No enrollable live hosts available — skipping.');

            return;
        }

        $transition = app(MenteeStageTransition::class);

        // Possible mentors: the program leader + any top-host-eligible live hosts.
        $mentorPool = User::where('role', 'live_host')
            ->where('is_top_host_eligible', true)
            ->pluck('id')
            ->prepend($leaderId)
            ->filter()
            ->unique()
            ->values();

        // Spread mentees across the journey: stage index + level + checklist progress.
        $plan = [
            ['stage' => 0, 'level' => 'Rookie', 'done' => 1, 'weeksAgo' => 1],
            ['stage' => 0, 'level' => 'Rookie', 'done' => 2, 'weeksAgo' => 1],
            ['stage' => 1, 'level' => 'Rising', 'done' => 3, 'weeksAgo' => 2],
            ['stage' => 1, 'level' => 'Rising', 'done' => 3, 'weeksAgo' => 2],
            ['stage' => 2, 'level' => 'Pro', 'done' => 4, 'weeksAgo' => 3],
            ['stage' => 2, 'level' => 'Pro', 'done' => 5, 'weeksAgo' => 3],
            ['stage' => 3, 'level' => 'Elite', 'done' => 5, 'weeksAgo' => 4],
            ['stage' => 'drop', 'level' => 'Rookie', 'done' => 1, 'weeksAgo' => 2],
        ];

        $created = 0;

        foreach ($plan as $i => $spec) {
            $host = $hosts->get($i);
            if (! $host) {
                break;
            }

            $enrolledAt = Carbon::now()->subWeeks($spec['weeksAgo'])->subDays($i);
            $mentorId = $mentorPool->isNotEmpty() ? $mentorPool[$i % $mentorPool->count()] : $leaderId;
            $level = $levels->firstWhere('name', $spec['level']);

            DB::transaction(function () use (
                $program, $host, $mentorId, $stages, $level,
                $spec, $enrolledAt, $leaderId, $transition, &$created
            ): void {
                $firstStage = $stages->first();

                $mentee = $program->mentees()->create([
                    'mentee_user_id' => $host->id,
                    'mentor_user_id' => $mentorId,
                    'mentee_number' => LiveHostMentee::generateMenteeNumber(),
                    'current_stage_id' => $firstStage->id,
                    'status' => 'active',
                    'enrolled_at' => $enrolledAt,
                    'level_id' => $level?->id,
                    'level_source' => $level ? 'manual' : null,
                    'level_assigned_at' => $level ? $enrolledAt : null,
                    'level_assigned_by' => $level ? $leaderId : null,
                ]);

                $transition->enterFirstStage($mentee);

                $mentee->history()->create([
                    'from_stage_id' => null,
                    'to_stage_id' => $firstStage->id,
                    'action' => 'enrolled',
                    'changed_by' => $leaderId,
                    'created_at' => $enrolledAt,
                    'updated_at' => $enrolledAt,
                ]);

                // Seed checklist from the program template.
                foreach (($program->checklist_template ?? []) as $pos => $item) {
                    if (empty($item['title'])) {
                        continue;
                    }
                    $isDone = $pos < $spec['done'];
                    $mentee->checklistItems()->create([
                        'title' => $item['title'],
                        'is_required' => (bool) ($item['is_required'] ?? true),
                        'position' => $pos,
                        'status' => $isDone ? 'done' : 'pending',
                        'completed_at' => $isDone ? $enrolledAt->copy()->addDays($pos + 1) : null,
                        'completed_by' => $isDone ? $mentorId : null,
                    ]);
                }

                // Advance the mentee stage-by-stage to its target stage.
                $targetIndex = $spec['stage'] === 'drop' ? 1 : (int) $spec['stage'];
                for ($s = 1; $s <= $targetIndex; $s++) {
                    $toStage = $stages->get($s);
                    if (! $toStage) {
                        break;
                    }
                    $when = $enrolledAt->copy()->addDays($s * 5);
                    $fromStageId = $mentee->current_stage_id;
                    $transition->transition($mentee, $toStage);
                    $mentee->history()->create([
                        'from_stage_id' => $fromStageId,
                        'to_stage_id' => $toStage->id,
                        'action' => 'advanced',
                        'notes' => "Progressed to {$toStage->name}.",
                        'changed_by' => $mentorId,
                        'created_at' => $when,
                        'updated_at' => $when,
                    ]);
                }

                // A couple of activity-log entries to make the timeline feel alive.
                $mentee->activities()->createMany([
                    [
                        'program_id' => $program->id,
                        'leader_user_id' => $leaderId,
                        'type' => 'check_in',
                        'title' => 'Welcome & onboarding check-in',
                        'notes' => 'Walked through program expectations and the stage roadmap.',
                        'occurred_at' => $enrolledAt->copy()->addDay(),
                        'created_by' => $mentorId,
                    ],
                    [
                        'program_id' => $program->id,
                        'leader_user_id' => $leaderId,
                        'type' => 'coaching',
                        'title' => 'Live performance coaching',
                        'notes' => 'Reviewed recent session GMV and audience retention.',
                        'occurred_at' => $enrolledAt->copy()->addDays(7),
                        'created_by' => $mentorId,
                    ],
                ]);

                // Drop this one to populate the "Dropped" tab.
                if ($spec['stage'] === 'drop') {
                    $droppedAt = $enrolledAt->copy()->addDays(10);
                    $mentee->history()->create([
                        'from_stage_id' => $mentee->current_stage_id,
                        'to_stage_id' => null,
                        'action' => 'dropped',
                        'notes' => 'Paused participation — personal reasons.',
                        'changed_by' => $leaderId,
                        'created_at' => $droppedAt,
                        'updated_at' => $droppedAt,
                    ]);
                    $transition->closeOpenRow($mentee);
                    $mentee->update(['status' => 'dropped']);
                }

                $created++;
            });
        }

        $this->command?->info("Seeded {$created} mentees into \"{$program->title}\".");
    }
}
