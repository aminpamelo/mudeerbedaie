<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveSession;
use App\Services\LiveHost\AutoVerifyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Undo auto-verifies that locked a session at a FALSE RM0. TikTok's live API
 * returns -1 for 24h-attributed GMV it has not published yet (window still open,
 * or the sales scope is restricted); the old auto-verifier read that as RM0 and
 * locked the session, so a real, high-viewership live shows zero sales. This
 * finds auto-verified sessions whose every linked live still carries the -1
 * sentinel and resets them to pending, so once the real GMV lands (a later sync
 * or the CSV export) the auto-verifier re-locks them with the correct figure.
 *
 * The GMV gate in AutoVerifyService now prevents new occurrences; this repairs
 * the ones already locked. Dry-run by default. Never touches a session a human
 * has verified/edited (verification history) or one in a locked payroll period.
 */
class RevertUnpublishedGmv extends Command
{
    protected $signature = 'livehost:revert-unpublished-gmv
        {--from= : Scan sessions scheduled from this date (Y-m-d)}
        {--until= : ...until this date (Y-m-d)}
        {--account= : Limit to one live_account_id}
        {--apply : Actually reset the fake-RM0 sessions to pending (default is read-only)}';

    protected $description = 'Find and reset auto-verified sessions locked at a false RM0 (TikTok GMV still -1 / unpublished).';

    public function handle(AutoVerifyService $service): int
    {
        $apply = (bool) $this->option('apply');

        $candidates = LiveSession::query()
            ->where('auto_verified', true)
            ->where('verification_status', 'verified')
            ->when($this->option('from'), fn ($q) => $q->whereDate('scheduled_start_at', '>=', CarbonImmutable::parse($this->option('from'))->toDateString()))
            ->when($this->option('until'), fn ($q) => $q->whereDate('scheduled_start_at', '<=', CarbonImmutable::parse($this->option('until'))->toDateString()))
            ->when($this->option('account'), fn ($q) => $q->where('live_account_id', (int) $this->option('account')))
            ->with(['actualLiveRecords', 'liveHost'])
            ->orderBy('scheduled_start_at')
            ->get();

        $targets = $candidates->filter(function (LiveSession $s) use ($service): bool {
            $records = $s->actualLiveRecords;

            if ($records->isEmpty()
                || $service->hasVerificationHistory($s)
                || $service->isPayrollLocked($s)) {
                return false;
            }

            // Every linked live still unpublished (-1): the locked GMV is a
            // fabricated zero, not a real one.
            return $records->every(fn (ActualLiveRecord $r) => (float) $r->live_attributed_gmv_myr < 0);
        })->values();

        if ($targets->isEmpty()) {
            $this->info("Scanned {$candidates->count()} auto-verified session(s) · no false-RM0 (unpublished GMV) sessions found.");

            return self::SUCCESS;
        }

        $this->table(
            ['session', 'scheduled', 'host', 'linked lives (launch · viewers)', 'locked GMV'],
            $targets->map(fn (LiveSession $s) => [
                $s->id,
                $s->scheduled_start_at?->format('Y-m-d H:i') ?? '—',
                $s->liveHost?->name ?? '—',
                $this->linkedLabel($s->actualLiveRecords),
                number_format((float) $s->gmv_amount, 2),
            ])->all(),
        );

        if (! $apply) {
            $this->newLine();
            $this->warn("{$targets->count()} session(s) locked at a false RM0 — re-run with --apply to reset them to pending.");

            return self::SUCCESS;
        }

        $reset = 0;
        foreach ($targets as $s) {
            $service->revertToPending($s);
            $reset++;
        }

        $this->info("Reset {$reset} session(s) to pending. They will auto-verify with the real GMV once TikTok/CSV publishes it (livehost:auto-verify-sessions).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, ActualLiveRecord>  $records
     */
    private function linkedLabel(Collection $records): string
    {
        return $records
            ->sortBy(fn (ActualLiveRecord $r) => $r->launched_time?->getTimestamp() ?? 0)
            ->map(fn (ActualLiveRecord $r) => sprintf(
                '%s·%s',
                $r->launched_time?->copy()->setTimezone('Asia/Kuala_Lumpur')->format('H:i') ?? '??',
                $r->viewers !== null ? number_format((int) $r->viewers) : '—',
            ))
            ->implode(', ');
    }
}
