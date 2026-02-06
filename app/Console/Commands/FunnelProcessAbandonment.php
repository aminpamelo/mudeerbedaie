<?php

namespace App\Console\Commands;

use App\Jobs\Funnel\DetectAbandonedSessions;
use App\Jobs\Funnel\ProcessCartAbandonment;
use Illuminate\Console\Command;

class FunnelProcessAbandonment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'funnel:process-abandonment
                            {--detect : Only detect abandoned sessions}
                            {--recover : Only process cart recovery emails}
                            {--sync : Run synchronously instead of dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process funnel cart abandonment detection and recovery emails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $detectOnly = $this->option('detect');
        $recoverOnly = $this->option('recover');
        $sync = $this->option('sync');

        // If no specific option, run both
        $runBoth = ! $detectOnly && ! $recoverOnly;

        if ($detectOnly || $runBoth) {
            $this->info('Detecting abandoned sessions...');

            if ($sync) {
                (new DetectAbandonedSessions)->handle();
            } else {
                DetectAbandonedSessions::dispatch();
            }

            $this->info('Abandoned session detection '.($sync ? 'completed' : 'dispatched'));
        }

        if ($recoverOnly || $runBoth) {
            $this->info('Processing cart abandonment recovery...');

            if ($sync) {
                (new ProcessCartAbandonment)->handle();
            } else {
                ProcessCartAbandonment::dispatch();
            }

            $this->info('Cart abandonment processing '.($sync ? 'completed' : 'dispatched'));
        }

        return Command::SUCCESS;
    }
}
