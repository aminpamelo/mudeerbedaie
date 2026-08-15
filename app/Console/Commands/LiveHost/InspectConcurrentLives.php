<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Read-only x-ray of "why does one creator show many lives at the same time" on
 * the Session Slots audit calendar. Groups actual_live_records by creator +
 * launch minute (KL) and prints every group with more than one live, showing
 * each row's source, TikTok live id, shop and GMV — so we can tell whether the
 * pile-up is a csv/api twin, the same broadcast split across shops, or genuinely
 * distinct lives. Never writes anything.
 */
class InspectConcurrentLives extends Command
{
    protected $signature = 'livehost:inspect-concurrent-lives
        {--date= : Only this KL date (Y-m-d). Omit to scan the last 30 days.}
        {--creator= : Filter to one creator handle (e.g. amarmirzabedaie)}
        {--minutes=5 : Treat launches within this many minutes as the same broadcast}
        {--min=2 : Only show groups with at least this many lives}';

    protected $description = 'Show creators with multiple lives at (nearly) the same launch time — diagnose calendar pile-ups.';

    public function handle(): int
    {
        $tz = 'Asia/Kuala_Lumpur';
        $window = max(1, (int) $this->option('minutes'));
        $min = max(2, (int) $this->option('min'));

        $query = ActualLiveRecord::query()
            ->whereNotNull('launched_time')
            ->with(['platformAccount:id,name'])
            ->orderBy('creator_handle')
            ->orderBy('launched_time');

        if ($date = $this->option('date')) {
            $start = Carbon::parse($date, $tz)->startOfDay()->utc();
            $end = Carbon::parse($date, $tz)->endOfDay()->utc();
            $query->whereBetween('launched_time', [$start, $end]);
        } else {
            $query->where('launched_time', '>=', now()->subDays(30));
        }

        if ($creator = $this->option('creator')) {
            $query->where('creator_handle', 'like', '%'.$creator.'%');
        }

        $records = $query->get([
            'id', 'source', 'source_record_id', 'platform_account_id',
            'creator_handle', 'creator_platform_user_id',
            'launched_time', 'ended_time', 'duration_seconds',
            'gmv_myr', 'live_attributed_gmv_myr', 'viewers',
        ]);

        if ($records->isEmpty()) {
            $this->info('No live records in that window.');

            return self::SUCCESS;
        }

        // Cluster per creator, then greedily by launch time within the window.
        $byCreator = $records->groupBy(fn ($r) => LiveAccount::normalizeHandle($r->creator_handle) ?? 'unknown');

        $groupsShown = 0;
        $totalExtra = 0;

        foreach ($byCreator as $handleKey => $rows) {
            $clusters = [];
            foreach ($rows->sortBy('launched_time') as $r) {
                $placed = false;
                foreach ($clusters as &$cluster) {
                    $anchor = $cluster[0]->launched_time;
                    if (abs($anchor->diffInMinutes($r->launched_time)) <= $window) {
                        $cluster[] = $r;
                        $placed = true;
                        break;
                    }
                }
                unset($cluster);
                if (! $placed) {
                    $clusters[] = [$r];
                }
            }

            foreach ($clusters as $cluster) {
                if (count($cluster) < $min) {
                    continue;
                }

                $groupsShown++;
                $totalExtra += count($cluster) - 1;

                $first = $cluster[0];
                $klTime = Carbon::instance($first->launched_time)->setTimezone($tz);
                $distinctIds = collect($cluster)->pluck('source_record_id')->filter()->unique()->count();
                $distinctShops = collect($cluster)->pluck('platform_account_id')->unique()->count();
                $distinctSources = collect($cluster)->pluck('source')->unique()->implode(',');
                $sumGmv = collect($cluster)->sum(fn ($r) => (float) $r->gmv_myr);

                $this->newLine();
                $this->line(sprintf(
                    '<fg=yellow>%s</> @ <fg=cyan>%s</> — <options=bold>%d lives</> · %d distinct live-ids · %d shops · sources=[%s] · ΣGMV=RM%s',
                    $first->creator_handle,
                    $klTime->format('Y-m-d h:i A'),
                    count($cluster),
                    $distinctIds,
                    $distinctShops,
                    $distinctSources,
                    number_format($sumGmv, 2),
                ));

                foreach ($cluster as $r) {
                    $rowKl = Carbon::instance($r->launched_time)->setTimezone($tz);
                    $endKl = $r->ended_time ? Carbon::instance($r->ended_time)->setTimezone($tz)->format('h:i A') : '—';
                    $this->line(sprintf(
                        '    #%-7d %-11s live_id=%-22s shop=%-4s(%s) %s→%s dur=%ss RM%s (attr RM%s) %sv',
                        $r->id,
                        $r->source,
                        $r->source_record_id ?? 'NULL',
                        $r->platform_account_id,
                        $r->platformAccount?->name ?? '?',
                        $rowKl->format('h:i A'),
                        $endKl,
                        $r->duration_seconds ?? '?',
                        number_format((float) $r->gmv_myr, 2),
                        number_format((float) $r->live_attributed_gmv_myr, 2),
                        $r->viewers ?? 0,
                    ));
                }
            }
        }

        $this->newLine();
        if ($groupsShown === 0) {
            $this->info('No creator has 2+ lives within the same launch window. The pile-up may be from genuinely spread-out lives.');
        } else {
            $this->warn(sprintf(
                '%d pile-up group(s) found · %d redundant-looking extra live(s). Read the "distinct live-ids / shops / sources" per group above to classify:',
                $groupsShown,
                $totalExtra,
            ));
            $this->line('  • sources=[csv_import,api_sync] & 1 distinct id  → CSV/API twin  → livehost:dedupe-live-sources');
            $this->line('  • many shops, many ids, source=api_sync           → same broadcast split per shop (needs merge, not delete)');
            $this->line('  • 1 shop, many ids                                → genuinely separate lives (correct) or re-broadcasts');
        }

        return self::SUCCESS;
    }
}
