<?php

namespace App\Console\Commands;

use App\Models\ClassSession;
use Illuminate\Console\Command;

class BackfillSessionAllowances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:backfill-allowances {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill allowance amounts for existing completed sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        $completedSessions = ClassSession::where('status', 'completed')
            ->whereNull('allowance_amount')
            ->with(['class', 'attendances'])
            ->get();

        if ($completedSessions->isEmpty()) {
            $this->info('No completed sessions found without allowance amounts.');

            return;
        }

        $this->info("Found {$completedSessions->count()} completed sessions to backfill.");

        $bar = $this->output->createProgressBar($completedSessions->count());
        $bar->start();

        $updated = 0;
        $errors = 0;

        foreach ($completedSessions as $session) {
            try {
                $allowanceAmount = $session->calculateTeacherAllowance();

                if (! $dryRun) {
                    $session->update(['allowance_amount' => $allowanceAmount]);
                }

                $updated++;
                $bar->advance();
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error processing session ID {$session->id}: ".$e->getMessage());
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run completed. Would have updated {$updated} sessions.");
        } else {
            $this->info("Successfully updated {$updated} sessions with allowance amounts.");
        }

        if ($errors > 0) {
            $this->warn("Encountered {$errors} errors during processing.");
        }
    }
}
