<?php

namespace App\Console\Commands;

use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Services\LiveHost\LiveAccountResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BackfillScheduleAccounts extends Command
{
    protected $signature = 'livehost:backfill-schedule-accounts {--dry-run : Report resolutions without writing}';

    protected $description = 'Resolve existing session slots and live sessions onto their canonical live_account_id (via pivot creator id/handle, else a unique host+shop pairing). Idempotent.';

    public function handle(LiveAccountResolver $resolver): int
    {
        $dry = (bool) $this->option('dry-run');

        $slots = $this->backfill(
            LiveScheduleAssignment::query()
                ->whereNull('live_account_id')
                ->with('liveHostPlatformAccount')
                ->get(),
            $resolver,
            $dry
        );

        $sessions = $this->backfill(
            LiveSession::query()
                ->whereNull('live_account_id')
                ->with('liveHostPlatformAccount')
                ->get(),
            $resolver,
            $dry
        );

        $this->table(
            ['Target', 'Resolved', 'Unresolved'],
            [
                ['Session slots', $slots['resolved'], $slots['unresolved']],
                ['Live sessions', $sessions['resolved'], $sessions['unresolved']],
            ]
        );

        if ($dry) {
            $this->info('Dry run — no changes written.');
        } else {
            $this->info('Backfill complete. Unresolved rows left null for manual review.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, LiveScheduleAssignment|LiveSession>  $rows
     * @return array{resolved:int, unresolved:int}
     */
    private function backfill(Collection $rows, LiveAccountResolver $resolver, bool $dry): array
    {
        $resolved = 0;
        $unresolved = 0;

        foreach ($rows as $row) {
            $account = $resolver->fromPivot($row->liveHostPlatformAccount)
                ?? $resolver->fromHostAndShop($row->live_host_id, $row->platform_account_id);

            if ($account === null) {
                $unresolved++;

                continue;
            }

            $resolved++;

            if (! $dry) {
                $this->assign($row, $account->id);
            }
        }

        return ['resolved' => $resolved, 'unresolved' => $unresolved];
    }

    private function assign(Model $row, int $accountId): void
    {
        $row->forceFill(['live_account_id' => $accountId])->saveQuietly();
    }
}
