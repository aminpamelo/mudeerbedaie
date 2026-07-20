<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\LiveSession;
use App\Services\LiveHost\AutoVerifyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Read-only x-ray of the auto-verify decision: for a session (or every pending
 * session in a date range) it prints which gate blocks it — no candidate live,
 * candidate already linked to another session, verification history, payroll
 * lock, or not-yet-settled — so a "why didn't this auto-verify?" is answerable
 * without guessing.
 */
class ExplainAutoVerify extends Command
{
    /**
     * @var string
     */
    protected $signature = 'livehost:explain-autoverify
        {--session= : A single live_session id to explain}
        {--from= : Scan pending sessions scheduled from this date (Y-m-d)}
        {--until= : ...until this date (Y-m-d)}
        {--host= : Limit the range scan to one live_host_id}
        {--apply : Actually verify every session whose verdict is WOULD VERIFY (default is read-only)}';

    /**
     * @var string
     */
    protected $description = 'Explain why the auto-verifier does or does not verify a session (or every pending session in a range).';

    public function handle(AutoVerifyService $service): int
    {
        $apply = (bool) $this->option('apply');

        if ($this->option('session')) {
            $session = LiveSession::find((int) $this->option('session'));

            if ($session === null) {
                $this->error("No session #{$this->option('session')}.");

                return self::FAILURE;
            }

            $this->render($service->explainSession($session));

            if ($apply && $service->verifyIfClear($session)) {
                $this->info("→ Verified session {$session->id}.");
            }

            return self::SUCCESS;
        }

        $from = $this->option('from');
        $until = $this->option('until');

        if (! $from || ! $until) {
            $this->error('Pass --session=ID, or both --from and --until (Y-m-d).');

            return self::INVALID;
        }

        $sessions = LiveSession::query()
            ->where('verification_status', 'pending')
            ->whereDate('scheduled_start_at', '>=', CarbonImmutable::parse($from)->toDateString())
            ->whereDate('scheduled_start_at', '<=', CarbonImmutable::parse($until)->toDateString())
            ->when($this->option('host'), fn ($q) => $q->where('live_host_id', (int) $this->option('host')))
            ->orderBy('scheduled_start_at')
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('No pending sessions in that range.');

            return self::SUCCESS;
        }

        $wouldVerify = 0;
        $applied = 0;

        $rows = $sessions->map(function (LiveSession $s) use ($service, $apply, &$wouldVerify, &$applied): array {
            $x = $service->explainSession($s);
            $clear = str_starts_with($x['verdict'], 'WOULD');
            $action = '';

            if ($clear) {
                $wouldVerify++;

                if ($apply && $service->verifyIfClear($s)) {
                    $applied++;
                    $action = ' → verified';
                }
            }

            return [
                $s->id,
                $s->scheduled_start_at?->format('m-d H:i') ?? '—',
                $x['candidate_count'],
                $x['suggested_cluster'] === [] ? '—' : implode(',', $x['suggested_cluster']),
                $x['held_by_other_sessions'] === [] ? '—' : implode(',', $x['held_by_other_sessions']),
                $x['verdict'].$action,
            ];
        })->all();

        $this->table(
            ['session', 'scheduled', 'cand', 'cluster live', 'held by', 'verdict'],
            $rows,
        );

        $this->newLine();
        $this->info($apply
            ? "{$sessions->count()} pending session(s) · verified {$applied}."
            : "{$sessions->count()} pending session(s) · {$wouldVerify} would verify — re-run with --apply to verify them.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $x
     */
    private function render(array $x): void
    {
        foreach ($x as $key => $value) {
            $shown = is_array($value) ? ($value === [] ? '—' : implode(', ', $value)) : (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value);
            $this->line(sprintf('  %-24s %s', $key, $shown));
        }
    }
}
