<?php

namespace Database\Seeders;

use App\Models\LiveAnalytics;
use App\Models\LiveSchedule;
use App\Models\LiveScheduleAssignment;
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

        // Step 3: Create live host users
        $hosts = $this->createLiveHosts();

        // Step 4: Create platform accounts and assign to hosts
        $platformAccounts = $this->createPlatformAccounts($hosts, $platforms);

        // Step 5: Create schedule assignments (new system)
        $this->createScheduleAssignments($hosts, $platformAccounts);

        // Step 6: Create legacy schedules (for backwards compatibility)
        $this->createLegacySchedules($platformAccounts);

        // Step 7: Create live sessions
        $this->createLiveSessions($platformAccounts);

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
     * Create live host users.
     */
    private function createLiveHosts(): array
    {
        $this->command->info('  → Creating live host users...');

        $hostsData = [
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
            [
                'name' => 'Muhammad Haziq',
                'email' => 'haziq@livehost.com',
                'phone' => '60112345678',
            ],
            [
                'name' => 'Nurul Aisyah',
                'email' => 'aisyah@livehost.com',
                'phone' => '60176543210',
            ],
        ];

        $hosts = [];

        foreach ($hostsData as $hostData) {
            // Check if phone is already taken by different user
            $existingUser = User::where('phone', $hostData['phone'])->first();
            if ($existingUser && $existingUser->email !== $hostData['email']) {
                $hostData['phone'] = '601'.rand(10000000, 99999999);
            }

            $hosts[] = User::firstOrCreate(
                ['email' => $hostData['email']],
                [
                    'name' => $hostData['name'],
                    'phone' => $hostData['phone'],
                    'password' => 'password',
                    'role' => 'live_host',
                    'status' => 'active',
                ]
            );
        }

        $this->command->info('    ✓ Created '.count($hosts).' live host users');

        return $hosts;
    }

    /**
     * Create platform accounts and assign to hosts via pivot table.
     */
    private function createPlatformAccounts(array $hosts, $platforms): array
    {
        $this->command->info('  → Creating platform accounts...');

        $platformAccounts = [];
        $pivotCount = 0;

        foreach ($platforms as $platform) {
            // Create 2-3 accounts per platform
            $numAccounts = rand(2, 3);

            for ($i = 1; $i <= $numAccounts; $i++) {
                $accountName = $platform->display_name.' Store '.$i;

                $account = PlatformAccount::firstOrCreate(
                    [
                        'platform_id' => $platform->id,
                        'name' => $accountName,
                    ],
                    [
                        'account_id' => '@'.strtolower(str_replace(' ', '', $platform->name)).'store'.$i,
                        'is_active' => true,
                        'currency' => 'MYR',
                        'country_code' => 'MY',
                    ]
                );

                $platformAccounts[] = $account;

                // Assign 1-2 random hosts to this account via pivot table
                $numHosts = rand(1, 2);
                $assignedHosts = collect($hosts)->random($numHosts);

                foreach ($assignedHosts as $host) {
                    // Check if relationship already exists
                    $exists = DB::table('live_host_platform_account')
                        ->where('user_id', $host->id)
                        ->where('platform_account_id', $account->id)
                        ->exists();

                    if (! $exists) {
                        DB::table('live_host_platform_account')->insert([
                            'user_id' => $host->id,
                            'platform_account_id' => $account->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $pivotCount++;
                    }
                }
            }
        }

        $this->command->info('    ✓ Created '.count($platformAccounts).' platform accounts');
        $this->command->info('    ✓ Created '.$pivotCount.' host-account assignments');

        return $platformAccounts;
    }

    /**
     * Create schedule assignments (new system with time slots).
     */
    private function createScheduleAssignments(array $hosts, array $platformAccounts): void
    {
        $this->command->info('  → Creating schedule assignments...');

        $timeSlots = LiveTimeSlot::where('is_active', true)->get();

        if ($timeSlots->isEmpty()) {
            $this->command->warn('    No time slots found. Skipping schedule assignments.');

            return;
        }

        $assignmentCount = 0;
        $statuses = ['scheduled', 'confirmed', 'in_progress', 'completed'];

        foreach ($platformAccounts as $account) {
            // Get hosts assigned to this account
            $accountHosts = DB::table('live_host_platform_account')
                ->where('platform_account_id', $account->id)
                ->pluck('user_id')
                ->toArray();

            if (empty($accountHosts)) {
                continue;
            }

            // Create template assignments (recurring weekly) for each day
            foreach (range(0, 6) as $dayOfWeek) {
                // Skip some days randomly (60% chance of having schedule on each day)
                if (rand(1, 100) > 60) {
                    continue;
                }

                // Select 1-3 time slots for this day
                $numSlots = rand(1, min(3, $timeSlots->count()));
                $selectedSlots = $timeSlots->random($numSlots);

                foreach ($selectedSlots as $slot) {
                    // Pick a random host from those assigned to this account
                    $hostId = $accountHosts[array_rand($accountHosts)];

                    // Check if assignment already exists
                    $exists = LiveScheduleAssignment::where('platform_account_id', $account->id)
                        ->where('time_slot_id', $slot->id)
                        ->where('day_of_week', $dayOfWeek)
                        ->where('is_template', true)
                        ->exists();

                    if (! $exists) {
                        LiveScheduleAssignment::create([
                            'platform_account_id' => $account->id,
                            'time_slot_id' => $slot->id,
                            'live_host_id' => $hostId,
                            'day_of_week' => $dayOfWeek,
                            'is_template' => true,
                            'status' => $statuses[rand(0, 2)], // scheduled, confirmed, or in_progress
                            'remarks' => rand(0, 1) ? 'Regular weekly session' : null,
                        ]);
                        $assignmentCount++;
                    }
                }
            }

            // Create some specific date assignments (non-template) for past and future dates
            for ($i = 0; $i < rand(2, 5); $i++) {
                $slot = $timeSlots->random();
                $hostId = $accountHosts[array_rand($accountHosts)];

                // Random date within past 2 weeks or next 2 weeks
                $isPast = rand(0, 1);
                $date = $isPast
                    ? Carbon::now()->subDays(rand(1, 14))
                    : Carbon::now()->addDays(rand(1, 14));

                $status = $isPast ? 'completed' : $statuses[rand(0, 1)];

                LiveScheduleAssignment::create([
                    'platform_account_id' => $account->id,
                    'time_slot_id' => $slot->id,
                    'live_host_id' => $hostId,
                    'day_of_week' => $date->dayOfWeek,
                    'schedule_date' => $date->toDateString(),
                    'is_template' => false,
                    'status' => $status,
                    'remarks' => $isPast ? 'Completed session' : 'Special event session',
                ]);
                $assignmentCount++;
            }
        }

        $this->command->info('    ✓ Created '.$assignmentCount.' schedule assignments');
    }

    /**
     * Create legacy schedules (for backwards compatibility).
     */
    private function createLegacySchedules(array $platformAccounts): void
    {
        $this->command->info('  → Creating legacy schedules...');

        $scheduleCount = 0;

        foreach ($platformAccounts as $account) {
            // Create 2-4 schedules per account
            $numSchedules = rand(2, 4);
            $usedDays = [];

            for ($i = 0; $i < $numSchedules; $i++) {
                // Pick a random day that hasn't been used
                do {
                    $dayOfWeek = rand(0, 6);
                } while (in_array($dayOfWeek, $usedDays));

                $usedDays[] = $dayOfWeek;

                // Random time between 10 AM and 10 PM
                $startHour = rand(10, 21);
                $startTime = sprintf('%02d:00:00', $startHour);
                $endTime = sprintf('%02d:00:00', min($startHour + rand(1, 3), 23));

                $schedule = LiveSchedule::firstOrCreate(
                    [
                        'platform_account_id' => $account->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $startTime,
                    ],
                    [
                        'end_time' => $endTime,
                        'is_recurring' => rand(0, 10) > 3, // 70% recurring
                        'is_active' => rand(0, 10) > 2, // 80% active
                    ]
                );

                if ($schedule->wasRecentlyCreated) {
                    $scheduleCount++;
                }
            }
        }

        $this->command->info('    ✓ Created '.$scheduleCount.' legacy schedules');
    }

    /**
     * Create live sessions with analytics.
     */
    private function createLiveSessions(array $platformAccounts): void
    {
        $this->command->info('  → Creating live sessions...');

        $sessionCount = 0;
        $analyticsCount = 0;

        foreach ($platformAccounts as $account) {
            $schedules = LiveSchedule::where('platform_account_id', $account->id)
                ->where('is_recurring', true)
                ->where('is_active', true)
                ->get();

            foreach ($schedules as $schedule) {
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

                    // Check if session already exists
                    $exists = LiveSession::where('platform_account_id', $account->id)
                        ->where('live_schedule_id', $schedule->id)
                        ->where('scheduled_start_at', $scheduledStart)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $session = LiveSession::create([
                        'platform_account_id' => $account->id,
                        'live_schedule_id' => $schedule->id,
                        'title' => 'Live Stream - '.$account->name,
                        'description' => 'Regular streaming session on '.$date->format('l, F j'),
                        'status' => $status,
                        'scheduled_start_at' => $scheduledStart,
                        'actual_start_at' => $isPast ? $scheduledStart : null,
                        'actual_end_at' => $isPast ? $scheduledStart->copy()->addHours(rand(1, 3)) : null,
                    ]);

                    $sessionCount++;

                    // Add analytics for completed sessions
                    if ($isPast) {
                        LiveAnalytics::create([
                            'live_session_id' => $session->id,
                            'viewers_peak' => rand(50, 500),
                            'viewers_avg' => rand(30, 300),
                            'total_likes' => rand(100, 1000),
                            'total_comments' => rand(50, 500),
                            'total_shares' => rand(10, 100),
                            'gifts_value' => rand(0, 1000) / 10,
                            'duration_minutes' => rand(60, 180),
                        ]);
                        $analyticsCount++;
                    }
                }
            }
        }

        $this->command->info('    ✓ Created '.$sessionCount.' live sessions');
        $this->command->info('    ✓ Created '.$analyticsCount.' analytics records');
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
                ['Schedule Assignments (New)', LiveScheduleAssignment::count()],
                ['Legacy Schedules', LiveSchedule::count()],
                ['Live Sessions', LiveSession::count()],
                ['Analytics Records', LiveAnalytics::count()],
            ]
        );
    }
}
