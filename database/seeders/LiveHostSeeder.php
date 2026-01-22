<?php

namespace Database\Seeders;

use App\Models\LiveAnalytics;
use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LiveHostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎬 Starting Live Host Management seeding...');

        // Step 1: Seed time slots first
        $this->seedTimeSlots();

        // Step 2: Get or create platforms
        $platforms = Platform::whereIn('name', ['TikTok Shop', 'Facebook Shop', 'Shopee'])->get();

        if ($platforms->isEmpty()) {
            $this->command->warn('No platforms found. Please ensure platforms are seeded first.');

            return;
        }

        // Step 3: Create live host users with their own platform accounts
        $this->createLiveHostsWithAccounts($platforms);

        $this->displaySummary();
    }

    /**
     * Seed the time slots.
     */
    private function seedTimeSlots(): void
    {
        $this->command->info('  → Creating time slots...');

        $timeSlots = [
            ['start_time' => '06:30:00', 'end_time' => '08:30:00', 'sort_order' => 1],
            ['start_time' => '08:30:00', 'end_time' => '10:30:00', 'sort_order' => 2],
            ['start_time' => '10:30:00', 'end_time' => '12:30:00', 'sort_order' => 3],
            ['start_time' => '12:30:00', 'end_time' => '14:30:00', 'sort_order' => 4],
            ['start_time' => '14:30:00', 'end_time' => '16:30:00', 'sort_order' => 5],
            ['start_time' => '17:00:00', 'end_time' => '19:00:00', 'sort_order' => 6],
            ['start_time' => '20:00:00', 'end_time' => '22:00:00', 'sort_order' => 7],
            ['start_time' => '22:00:00', 'end_time' => '00:00:00', 'sort_order' => 8],
        ];

        foreach ($timeSlots as $slot) {
            LiveTimeSlot::updateOrCreate(
                [
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ],
                [
                    'is_active' => true,
                    'sort_order' => $slot['sort_order'],
                    'duration_minutes' => $this->calculateDuration($slot['start_time'], $slot['end_time']),
                ]
            );
        }

        $this->command->info('    ✓ Created '.LiveTimeSlot::count().' time slots');
    }

    /**
     * Calculate duration in minutes between two times.
     */
    private function calculateDuration(string $startTime, string $endTime): int
    {
        $start = Carbon::createFromFormat('H:i:s', $startTime);
        $end = Carbon::createFromFormat('H:i:s', $endTime);

        if ($end->lt($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    /**
     * Create live hosts with their own platform accounts, schedules, and sessions.
     */
    private function createLiveHostsWithAccounts($platforms): void
    {
        $this->command->info('  → Creating live hosts with accounts...');

        // Define hosts with their platform preferences
        $hostsData = [
            [
                'name' => 'Test Live Host',
                'email' => 'test@example.com',
                'phone' => '60100000000',
                'platforms' => ['TikTok Shop', 'Facebook Shop'], // Will get accounts on these platforms
            ],
            [
                'name' => 'Sarah Chen',
                'email' => 'sarah@livehost.com',
                'phone' => '60123456789',
                'platforms' => ['TikTok Shop', 'Shopee'],
            ],
            [
                'name' => 'Ahmad Rahman',
                'email' => 'ahmad@livehost.com',
                'phone' => '60187654321',
                'platforms' => ['Facebook Shop', 'Shopee'],
            ],
            [
                'name' => 'Lisa Tan',
                'email' => 'lisa@livehost.com',
                'phone' => '60198765432',
                'platforms' => ['TikTok Shop'],
            ],
            [
                'name' => 'Muhammad Haziq',
                'email' => 'haziq@livehost.com',
                'phone' => '60112345678',
                'platforms' => ['Shopee', 'Facebook Shop'],
            ],
        ];

        $hostCount = 0;
        $accountCount = 0;
        $scheduleCount = 0;
        $sessionCount = 0;

        foreach ($hostsData as $hostData) {
            // Create or update host user
            $existingPhoneUser = User::where('phone', $hostData['phone'])->first();
            if ($existingPhoneUser && $existingPhoneUser->email !== $hostData['email']) {
                $hostData['phone'] = '601'.rand(10000000, 99999999);
            }

            $host = User::where('email', $hostData['email'])->first();

            if ($host) {
                $host->update([
                    'role' => 'live_host',
                    'status' => 'active',
                ]);
            } else {
                $host = User::create([
                    'name' => $hostData['name'],
                    'email' => $hostData['email'],
                    'phone' => $hostData['phone'],
                    'password' => 'password',
                    'role' => 'live_host',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }

            $hostCount++;
            $this->command->info("    → Processing host: {$host->name} ({$host->email})");

            // Create platform accounts for this host
            foreach ($hostData['platforms'] as $platformName) {
                $platform = $platforms->firstWhere('name', $platformName);
                if (! $platform) {
                    continue;
                }

                // Create unique account for this host on this platform
                $accountName = $host->name.' - '.$platform->display_name;
                $accountId = '@'.strtolower(str_replace([' ', '-'], '', $host->name)).'_'.strtolower(str_replace(' ', '', $platform->name));

                $account = PlatformAccount::firstOrCreate(
                    [
                        'platform_id' => $platform->id,
                        'name' => $accountName,
                    ],
                    [
                        'account_id' => $accountId,
                        'is_active' => true,
                        'currency' => 'MYR',
                        'country_code' => 'MY',
                    ]
                );

                $accountCount++;

                // Link host to account via pivot table
                $pivotExists = DB::table('live_host_platform_account')
                    ->where('user_id', $host->id)
                    ->where('platform_account_id', $account->id)
                    ->exists();

                if (! $pivotExists) {
                    DB::table('live_host_platform_account')->insert([
                        'user_id' => $host->id,
                        'platform_account_id' => $account->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Create schedules for this account
                $scheduleCount += $this->createSchedulesForAccount($account);

                // Create sessions for this host on this account
                $sessionCount += $this->createSessionsForHost($host, $account);
            }
        }

        $this->command->info("    ✓ Created {$hostCount} live hosts");
        $this->command->info("    ✓ Created {$accountCount} platform accounts");
        $this->command->info("    ✓ Created {$scheduleCount} schedules");
        $this->command->info("    ✓ Created {$sessionCount} sessions");
    }

    /**
     * Create legacy schedules for an account.
     */
    private function createSchedulesForAccount(PlatformAccount $account): int
    {
        $count = 0;
        $daysOfWeek = [1, 2, 3, 4, 5]; // Monday to Friday

        foreach ($daysOfWeek as $day) {
            // Skip some days randomly
            if (rand(0, 100) > 70) {
                continue;
            }

            $startHour = rand(10, 20);
            $startTime = sprintf('%02d:00:00', $startHour);
            $endTime = sprintf('%02d:00:00', min($startHour + 2, 23));

            $schedule = LiveSchedule::firstOrCreate(
                [
                    'platform_account_id' => $account->id,
                    'day_of_week' => $day,
                    'start_time' => $startTime,
                ],
                [
                    'end_time' => $endTime,
                    'is_recurring' => true,
                    'is_active' => true,
                ]
            );

            if ($schedule->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Create sessions for a host on a specific account.
     */
    private function createSessionsForHost(User $host, PlatformAccount $account): int
    {
        $count = 0;

        $schedules = LiveSchedule::where('platform_account_id', $account->id)
            ->where('is_active', true)
            ->get();

        foreach ($schedules as $schedule) {
            // Create past sessions (some pending upload, some completed)
            $pastDates = [
                now()->subWeeks(3)->next($schedule->day_of_week),
                now()->subWeeks(2)->next($schedule->day_of_week),
                now()->subWeeks(1)->next($schedule->day_of_week),
            ];

            foreach ($pastDates as $index => $date) {
                $scheduledStart = $date->copy()->setTimeFromTimeString($schedule->start_time);

                // Check if session already exists
                $exists = LiveSession::where('platform_account_id', $account->id)
                    ->where('live_host_id', $host->id)
                    ->where('scheduled_start_at', $scheduledStart)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $actualStart = $scheduledStart->copy();
                $actualEnd = $scheduledStart->copy()->addHours(rand(1, 2));
                $durationMinutes = $actualStart->diffInMinutes($actualEnd);

                // First 2 sessions are pending upload, third is uploaded
                $isUploaded = ($index === 2);

                $session = LiveSession::create([
                    'platform_account_id' => $account->id,
                    'live_schedule_id' => $schedule->id,
                    'live_host_id' => $host->id,
                    'title' => 'Live Stream - '.$account->name,
                    'description' => 'Session on '.$date->format('l, F j'),
                    'status' => 'ended',
                    'scheduled_start_at' => $scheduledStart,
                    'actual_start_at' => $actualStart,
                    'actual_end_at' => $actualEnd,
                    'duration_minutes' => $durationMinutes,
                    'uploaded_at' => $isUploaded ? now() : null,
                    'uploaded_by' => $isUploaded ? $host->id : null,
                    'remarks' => $isUploaded ? 'Great session!' : null,
                ]);

                $count++;

                // Add analytics for uploaded sessions
                if ($isUploaded) {
                    LiveAnalytics::create([
                        'live_session_id' => $session->id,
                        'viewers_peak' => rand(100, 500),
                        'viewers_avg' => rand(50, 300),
                        'total_likes' => rand(200, 1000),
                        'total_comments' => rand(50, 300),
                        'total_shares' => rand(10, 50),
                        'gifts_value' => rand(50, 500),
                        'duration_minutes' => $durationMinutes,
                    ]);
                }
            }

            // Create upcoming sessions
            $futureDates = [
                now()->addWeeks(1)->next($schedule->day_of_week),
                now()->addWeeks(2)->next($schedule->day_of_week),
            ];

            foreach ($futureDates as $date) {
                $scheduledStart = $date->copy()->setTimeFromTimeString($schedule->start_time);

                $exists = LiveSession::where('platform_account_id', $account->id)
                    ->where('live_host_id', $host->id)
                    ->where('scheduled_start_at', $scheduledStart)
                    ->exists();

                if ($exists) {
                    continue;
                }

                LiveSession::create([
                    'platform_account_id' => $account->id,
                    'live_schedule_id' => $schedule->id,
                    'live_host_id' => $host->id,
                    'title' => 'Upcoming Stream - '.$account->name,
                    'description' => 'Scheduled for '.$date->format('l, F j'),
                    'status' => 'scheduled',
                    'scheduled_start_at' => $scheduledStart,
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Display summary of seeded data.
     */
    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('✅ Live Host Management seeding completed!');
        $this->command->newLine();

        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Live Host Users', User::where('role', 'live_host')->count()],
                ['Time Slots', LiveTimeSlot::count()],
                ['Platform Accounts', PlatformAccount::count()],
                ['Host-Account Assignments', DB::table('live_host_platform_account')->count()],
                ['Legacy Schedules', LiveSchedule::count()],
                ['Live Sessions', LiveSession::count()],
                ['- Pending Upload', LiveSession::where('status', 'ended')->whereNull('uploaded_at')->count()],
                ['- Uploaded', LiveSession::whereNotNull('uploaded_at')->count()],
                ['- Scheduled', LiveSession::where('status', 'scheduled')->count()],
                ['Analytics Records', LiveAnalytics::count()],
            ]
        );

        // Show test host specific info
        $testHost = User::where('email', 'test@example.com')->first();
        if ($testHost) {
            $this->command->newLine();
            $this->command->info('📧 Test Host (test@example.com):');
            $pendingUpload = LiveSession::where('live_host_id', $testHost->id)
                ->where('status', 'ended')
                ->whereNull('uploaded_at')
                ->count();
            $this->command->info("   - Sessions pending upload: {$pendingUpload}");
            $this->command->info('   - Login: test@example.com / password');
        }
    }
}
