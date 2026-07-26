<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restore live_sessions.scheduled_start_at from the real schedule. The column was
 * TIMESTAMP ON UPDATE CURRENT_TIMESTAMP on MySQL, so every session write bumped it
 * to now() — leaving the schedule anchor pointing at random past moments. The true
 * scheduled start is derivable from the (drift-immune) assignment: its
 * schedule_date DATE + the time slot's start_time. This rebuilds it for every
 * session that has a dated assignment + slot.
 *
 * Run AFTER the migration that strips the ON UPDATE behaviour. Dry-run by default.
 */
class RestoreScheduledTimes extends Command
{
    protected $signature = 'livehost:restore-scheduled-times
        {--apply : Actually write the corrected scheduled_start_at (default is a read-only count)}';

    protected $description = 'Rebuild live_sessions.scheduled_start_at from assignment schedule_date + slot start_time.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $rows = DB::table('live_sessions as s')
            ->join('live_schedule_assignments as a', 'a.id', '=', 's.live_schedule_assignment_id')
            ->join('live_time_slots as ts', 'ts.id', '=', 'a.time_slot_id')
            ->whereNotNull('a.schedule_date')
            ->whereNotNull('ts.start_time')
            ->select('s.id', 's.scheduled_start_at', 'a.schedule_date', 'ts.start_time')
            ->orderBy('s.id')
            ->get();

        $fixed = 0;
        $checked = 0;

        foreach ($rows as $row) {
            $checked++;
            $correct = CarbonImmutable::parse(
                substr((string) $row->schedule_date, 0, 10).' '.substr((string) $row->start_time, 0, 8)
            )->format('Y-m-d H:i:s');

            $current = $row->scheduled_start_at
                ? CarbonImmutable::parse($row->scheduled_start_at)->format('Y-m-d H:i:s')
                : null;

            if ($current === $correct) {
                continue;
            }

            if ($apply) {
                LiveSession::where('id', $row->id)->update(['scheduled_start_at' => $correct]);
            }
            $fixed++;
        }

        if (! $apply) {
            $this->warn('DRY RUN — no rows changed. Pass --apply to restore.');
        }

        $this->info(sprintf(
            '%s %d of %d dated session(s) to their real scheduled_start_at (assignment date + slot start).',
            $apply ? 'Restored' : 'Would restore',
            $fixed,
            $checked,
        ));

        return self::SUCCESS;
    }
}
