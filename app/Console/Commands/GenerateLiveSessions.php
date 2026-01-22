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
    protected $description = 'Generate live sessions from active schedules (both admin assigned and self scheduled)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $generated = 0;

        $this->info("Generating sessions for next {$days} days...");

        // Generate from all active LiveSchedules (both admin assigned and self scheduled)
        $generated += $this->generateFromLiveSchedules($days);

        $this->info("Generated {$generated} new sessions.");

        return Command::SUCCESS;
    }

    /**
     * Generate sessions from LiveSchedule (both admin assigned and self scheduled)
     */
    protected function generateFromLiveSchedules(int $days): int
    {
        // Get all active schedules (recurring for weekly, or one-time schedules)
        $schedules = LiveSchedule::active()
            ->with(['platformAccount', 'liveHost'])
            ->get();

        $generated = 0;

        $this->line("\n--- Generating from Live Schedules ---");

        foreach ($schedules as $schedule) {
            $startDate = now()->startOfDay();
            $endDate = now()->addDays($days);

            // For recurring schedules, generate for each matching day
            if ($schedule->is_recurring) {
                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    if ($date->dayOfWeek === $schedule->day_of_week) {
                        $generated += $this->createSessionIfNotExists($schedule, $date);
                    }
                }
            } else {
                // For non-recurring, only generate for the next matching day
                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    if ($date->dayOfWeek === $schedule->day_of_week) {
                        $generated += $this->createSessionIfNotExists($schedule, $date);
                        break; // Only one session for non-recurring
                    }
                }
            }
        }

        return $generated;
    }

    /**
     * Create a session if it doesn't already exist
     */
    protected function createSessionIfNotExists(LiveSchedule $schedule, Carbon $date): int
    {
        $scheduledTime = Carbon::parse($date->format('Y-m-d').' '.$schedule->start_time);

        // Only create future sessions
        if (! $scheduledTime->isFuture()) {
            return 0;
        }

        // Check if session already exists for this schedule at this time
        $exists = LiveSession::where('platform_account_id', $schedule->platform_account_id)
            ->where('live_host_id', $schedule->live_host_id)
            ->where('scheduled_start_at', $scheduledTime)
            ->exists();

        if ($exists) {
            return 0;
        }

        // Determine source label
        $sourceLabel = $schedule->is_recurring ? 'Recurring' : 'One-time';

        LiveSession::create([
            'platform_account_id' => $schedule->platform_account_id,
            'live_schedule_id' => $schedule->id,
            'live_host_id' => $schedule->live_host_id,
            'title' => 'Live Stream - '.$schedule->day_name,
            'scheduled_start_at' => $scheduledTime,
            'status' => 'scheduled',
        ]);

        $hostName = $schedule->liveHost?->name ?? 'Unassigned';
        $accountName = $schedule->platformAccount?->name ?? 'Unknown';
        $this->line("✓ [{$sourceLabel}] {$accountName} ({$hostName}) on {$scheduledTime->format('Y-m-d H:i')}");

        return 1;
    }
}
