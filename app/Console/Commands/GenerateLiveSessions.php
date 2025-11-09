<?php

namespace App\Console\Commands;

use App\Models\LiveSchedule;
use App\Models\LiveSession;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateLiveSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live:generate-sessions {--days=7 : Number of days ahead to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate live sessions from active recurring schedules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $schedules = LiveSchedule::active()->recurring()->with('platformAccount')->get();
        $generated = 0;

        $this->info("Generating sessions for next {$days} days...");

        foreach ($schedules as $schedule) {
            $startDate = now()->startOfWeek();
            $endDate = now()->addDays($days);

            // Loop through each day in the range
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                // Check if this day matches the schedule
                if ($date->dayOfWeek === $schedule->day_of_week) {
                    $scheduledTime = Carbon::parse($date->format('Y-m-d').' '.$schedule->start_time);

                    // Only generate if it's in the future
                    if ($scheduledTime->isFuture()) {
                        // Check if session already exists
                        $exists = LiveSession::where('platform_account_id', $schedule->platform_account_id)
                            ->where('scheduled_start_at', $scheduledTime)
                            ->exists();

                        if (! $exists) {
                            LiveSession::create([
                                'platform_account_id' => $schedule->platform_account_id,
                                'live_schedule_id' => $schedule->id,
                                'title' => 'Live Stream - '.$schedule->day_name,
                                'scheduled_start_at' => $scheduledTime,
                                'status' => 'scheduled',
                            ]);

                            $generated++;
                            $this->line("✓ Generated session for {$schedule->platformAccount->name} on {$scheduledTime->format('Y-m-d H:i')}");
                        }
                    }
                }
            }
        }

        $this->info("Generated {$generated} new sessions.");

        return Command::SUCCESS;
    }
}
