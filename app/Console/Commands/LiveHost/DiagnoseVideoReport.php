<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic for the Video Report showing 0 videos. Explains WHY a
 * program's grid is empty by checking the three things that can hide videos:
 * they were never logged, they are attached to a different program's mentee
 * row, or their video_date falls outside the report window. Writes nothing.
 */
class DiagnoseVideoReport extends Command
{
    /**
     * @var string
     */
    protected $signature = 'livehost:video-report-diagnose
        {--year= : Report year (default: current)}
        {--from= : First month 1-12 (default: 1)}
        {--to= : Last month 1-12 (default: 12)}';

    /**
     * @var string
     */
    protected $description = 'Explain why the Video Report shows 0 videos for the active mentoring program(s). Read-only.';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: now()->format('Y'));
        $from = (int) ($this->option('from') ?: 1);
        $to = (int) ($this->option('to') ?: 12);

        $start = CarbonImmutable::create($year, $from, 1)->startOfMonth();
        $end = CarbonImmutable::create($year, $to, 1)->endOfMonth();

        $this->info("Video Report diagnostic — window {$start->format('M Y')} … {$end->format('M Y')}");
        $this->line('Total videos in DB: '.LiveHostMenteeDailyVideo::count());
        $this->line('App timezone: '.config('app.timezone').' | now: '.now());
        $this->newLine();

        $programs = LiveHostMentoringProgram::query()->where('status', 'active')->orderBy('title')->get(['id', 'title']);

        if ($programs->isEmpty()) {
            $this->warn('No ACTIVE programs — the report renders nothing. Check program statuses.');

            return self::SUCCESS;
        }

        foreach ($programs as $program) {
            $this->diagnoseProgram($program, $start, $end);
        }

        $this->newLine();
        $this->line('Video count by month (whole DB):');
        $byMonth = LiveHostMenteeDailyVideo::query()->get(['video_date'])
            ->groupBy(fn ($v) => $v->video_date?->format('Y-m'))
            ->map->count()
            ->sortKeys();
        foreach ($byMonth as $month => $count) {
            $this->line("  {$month}: {$count}");
        }

        return self::SUCCESS;
    }

    private function diagnoseProgram(LiveHostMentoringProgram $program, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $this->newLine();
        $this->info("PROGRAM #{$program->id} — {$program->title}");

        // Mentees shown on the report (active + graduated).
        $reportMentees = LiveHostMentee::query()
            ->where('program_id', $program->id)
            ->whereIn('status', ['active', 'graduated'])
            ->get(['id', 'mentee_user_id', 'status']);

        $statusBreakdown = LiveHostMentee::query()->where('program_id', $program->id)
            ->get(['status'])->groupBy('status')->map->count();
        $this->line('  Mentees on report (active/graduated): '.$reportMentees->count());
        $this->line('  All mentee statuses in program: '.$statusBreakdown->map(fn ($c, $s) => "{$s}={$c}")->implode(', '));

        $menteeIds = $reportMentees->pluck('id');
        $userIds = $reportMentees->pluck('mentee_user_id')->filter()->unique();

        // 1. Videos attached to THIS program's mentee rows — the report's source.
        $linked = LiveHostMenteeDailyVideo::query()->whereIn('mentee_id', $menteeIds->all());
        $linkedTotal = (clone $linked)->count();
        $linkedInWindow = (clone $linked)
            ->whereBetween('video_date', [$start->toDateTimeString(), $end->toDateTimeString()])->count();
        $this->line("  → Videos linked to these mentees: {$linkedTotal} total, {$linkedInWindow} inside the window");

        if ($linkedTotal > 0) {
            $range = (clone $linked)->selectRaw('MIN(video_date) mn, MAX(video_date) mx')->first();
            $this->line("    (their video_date range: {$range->mn} … {$range->mx})");
        }

        // 2. Cross-program leak: these HOST USERS logged videos, but under a
        //    DIFFERENT program's mentee row (e.g. a previous batch) — invisible here.
        $elsewhere = LiveHostMenteeDailyVideo::query()
            ->whereHas('mentee', fn ($q) => $q->whereIn('mentee_user_id', $userIds->all())->where('program_id', '!=', $program->id))
            ->with('mentee.program:id,title')
            ->get();
        if ($elsewhere->isNotEmpty()) {
            $this->warn("  ⚠ {$elsewhere->count()} video(s) by these hosts are attached to OTHER programs' mentee rows:");
            $elsewhere->groupBy(fn ($v) => $v->mentee?->program?->title ?? 'unknown')
                ->each(fn ($g, $title) => $this->line("      {$g->count()} under \"{$title}\""));
        }

        // 3. Enrollment resolution: when a host logs in Pocket, the video attaches
        //    to activeMenteeEnrollment(). Confirm that points back to THIS program.
        $resolvesHere = 0;
        $resolvesElsewhere = 0;
        $resolvesNull = 0;
        foreach ($userIds as $uid) {
            $active = User::find($uid)?->activeMenteeEnrollment()->first();
            if ($active === null) {
                $resolvesNull++;
            } elseif ((int) $active->program_id === (int) $program->id) {
                $resolvesHere++;
            } else {
                $resolvesElsewhere++;
            }
        }
        $this->line("  Pocket logging target (activeMenteeEnrollment): {$resolvesHere} → this program, {$resolvesElsewhere} → another program, {$resolvesNull} → none (cannot log)");

        // Verdict.
        if ($linkedInWindow > 0) {
            $this->line('  VERDICT: videos DO exist in-window — the report should not be 0. Re-check the UI filters.');
        } elseif ($linkedTotal > 0) {
            $this->warn('  VERDICT: videos exist but ALL fall OUTSIDE the selected window — adjust the month range.');
        } elseif ($elsewhere->isNotEmpty()) {
            $this->warn('  VERDICT: hosts logged videos, but under a different program\'s enrollment — not this batch.');
        } elseif ($resolvesNull > 0 || $resolvesElsewhere > 0) {
            $this->warn('  VERDICT: some hosts have no active enrollment on this program, so Pocket cannot log against it.');
        } else {
            $this->line('  VERDICT: no videos logged yet for this program — expected 0 until hosts log in Pocket.');
        }
    }
}
