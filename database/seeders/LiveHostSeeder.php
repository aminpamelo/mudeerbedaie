<?php

namespace Database\Seeders;

use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

class LiveHostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create platforms
        $platforms = Platform::whereIn('name', ['TikTok Shop', 'Facebook Shop', 'Shopee'])->get();

        if ($platforms->isEmpty()) {
            $this->command->warn('No platforms found. Please ensure platforms are seeded first.');

            return;
        }

        // Create live host users
        $hosts = [
            [
                'name' => 'Sarah Chen',
                'email' => 'sarah@livehost.com',
                'phone' => '60123456789',
            ],
            [
                'name' => 'Ahmad Rahman',
                'email' => 'ahmad@livehost.com',
                'phone' => '60187654321',
            ],
            [
                'name' => 'Lisa Tan',
                'email' => 'lisa@livehost.com',
                'phone' => '60198765432',
            ],
        ];

        // Check if phone numbers are already taken and modify if needed
        foreach ($hosts as &$hostData) {
            $existingUser = User::where('phone', $hostData['phone'])->first();
            if ($existingUser && $existingUser->email !== $hostData['email']) {
                // Phone already taken by different user, generate new one
                $hostData['phone'] = '601'.rand(10000000, 99999999);
            }
        }

        foreach ($hosts as $hostData) {
            // Create or get live host user
            $host = User::firstOrCreate(
                ['email' => $hostData['email']],
                [
                    'name' => $hostData['name'],
                    'phone' => $hostData['phone'],
                    'password' => 'password',
                    'role' => 'live_host',
                    'status' => 'active',
                ]
            );

            $this->command->info("Created/Updated live host: {$host->name}");

            // Create 2-3 platform accounts for each host
            $numAccounts = rand(2, 3);
            $selectedPlatforms = $platforms->random(min($numAccounts, $platforms->count()));

            foreach ($selectedPlatforms as $platform) {
                $account = PlatformAccount::firstOrCreate(
                    [
                        'user_id' => $host->id,
                        'platform_id' => $platform->id,
                    ],
                    [
                        'name' => "{$host->name}'s {$platform->display_name}",
                        'account_id' => '@'.strtolower(str_replace(' ', '', $host->name)).rand(100, 999),
                        'is_active' => true,
                    ]
                );

                $this->command->info("  - Created platform account: {$account->name}");

                // Create 2-4 schedules for each account (spread across the week)
                $numSchedules = rand(2, 4);
                $usedDays = [];

                for ($i = 0; $i < $numSchedules; $i++) {
                    // Pick a random day that hasn't been used yet
                    do {
                        $dayOfWeek = rand(0, 6);
                    } while (in_array($dayOfWeek, $usedDays));

                    $usedDays[] = $dayOfWeek;

                    // Random time between 10 AM and 10 PM
                    $startHour = rand(10, 21);
                    $startTime = sprintf('%02d:00:00', $startHour);
                    $endTime = sprintf('%02d:00:00', min($startHour + rand(1, 3), 23));

                    $schedule = LiveSchedule::create([
                        'platform_account_id' => $account->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'is_recurring' => rand(0, 10) > 3, // 70% chance of recurring
                        'is_active' => rand(0, 10) > 2, // 80% chance of active
                    ]);

                    $this->command->info("    - Created schedule: {$schedule->day_name} {$schedule->time_range}");

                    // Create some sessions for this schedule (past and upcoming)
                    if ($schedule->is_recurring && $schedule->is_active) {
                        // Create 2 past sessions and 2 upcoming sessions
                        $dates = [
                            now()->subWeeks(2)->next($schedule->day_of_week),
                            now()->subWeeks(1)->next($schedule->day_of_week),
                            now()->addWeeks(1)->next($schedule->day_of_week),
                            now()->addWeeks(2)->next($schedule->day_of_week),
                        ];

                        foreach ($dates as $date) {
                            $isPast = $date->isPast();
                            $status = $isPast ? 'ended' : 'scheduled';

                            $scheduledStart = $date->copy()->setTimeFromTimeString($schedule->start_time);

                            $session = LiveSession::create([
                                'platform_account_id' => $account->id,
                                'live_schedule_id' => $schedule->id,
                                'title' => "Live Stream - {$platform->display_name}",
                                'description' => "Regular streaming session on {$date->format('l, F j')}",
                                'status' => $status,
                                'scheduled_start_at' => $scheduledStart,
                                'actual_start_at' => $isPast ? $scheduledStart : null,
                                'actual_end_at' => $isPast ? $scheduledStart->copy()->addHours(rand(1, 3)) : null,
                            ]);

                            // Add analytics for completed sessions
                            if ($isPast) {
                                $session->analytics()->create([
                                    'viewers_peak' => rand(50, 500),
                                    'viewers_avg' => rand(30, 300),
                                    'total_likes' => rand(100, 1000),
                                    'total_comments' => rand(50, 500),
                                    'total_shares' => rand(10, 100),
                                    'gifts_value' => rand(0, 1000) / 10, // Random value up to 100.00
                                    'duration_minutes' => rand(60, 180),
                                ]);
                            }
                        }
                    }
                }
            }
        }

        $this->command->info('✅ Live Host seeding completed successfully!');
        $this->command->info('Created:');
        $this->command->info('  - '.User::where('role', 'live_host')->count().' live host users');
        $this->command->info('  - '.PlatformAccount::whereHas('user', fn ($q) => $q->where('role', 'live_host'))->count().' platform accounts');
        $this->command->info('  - '.LiveSchedule::count().' live schedules');
        $this->command->info('  - '.LiveSession::count().' live sessions');
    }
}
