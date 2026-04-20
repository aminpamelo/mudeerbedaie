<?php

namespace App\Console\Commands;

use App\Models\LiveHostPayrollRun;
use App\Models\User;
use App\Services\LiveHost\LiveHostPayrollService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateLiveHostPayrollDraft extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'livehost:payroll-draft {--period= : Payroll period in YYYY-MM format} {--force : Regenerate when a draft run already exists for the period}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a draft live-host payroll run for the given YYYY-MM period.';

    /**
     * Execute the console command.
     *
     * Parses the --period flag as YYYY-MM, derives period_start / period_end,
     * refuses to overwrite an existing run (with special-case --force for
     * drafts), and delegates item computation to LiveHostPayrollService.
     */
    public function handle(LiveHostPayrollService $service): int
    {
        $period = (string) $this->option('period');

        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Invalid --period. Expected format YYYY-MM (e.g. 2026-04).');

            return Command::FAILURE;
        }

        try {
            $periodStart = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        } catch (\Throwable $e) {
            $this->error('Invalid --period. Expected format YYYY-MM (e.g. 2026-04).');

            return Command::FAILURE;
        }

        $periodEnd = $periodStart->copy()->endOfMonth();

        $existing = LiveHostPayrollRun::query()
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();

        if ($existing !== null) {
            if (! $this->option('force')) {
                $this->error("A payroll run for {$period} already exists (status: {$existing->status}). Use --force to regenerate (will delete existing run).");

                return Command::FAILURE;
            }

            if ($existing->status !== 'draft') {
                $this->error("Cannot regenerate: existing payroll run for {$period} is {$existing->status}. Only draft runs may be regenerated via --force.");

                return Command::FAILURE;
            }

            $existing->items()->delete();
            $existing->delete();
        }

        $actor = User::query()->where('role', 'admin')->first();

        if ($actor === null) {
            $this->error('No admin user found; create one first.');

            return Command::FAILURE;
        }

        $run = $service->generateDraft($periodStart, $periodEnd, $actor);

        $itemCount = $run->items->count();
        $total = number_format((float) $run->items->sum('net_payout_myr'), 2, '.', '');

        $this->info("Generated draft payroll run #{$run->id} for {$period} with {$itemCount} items. Total payout: RM {$total}.");

        return Command::SUCCESS;
    }
}
