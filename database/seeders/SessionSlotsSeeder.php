<?php

namespace Database\Seeders;

use App\Models\LiveSession;
use App\Models\LiveSchedule;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SessionSlotsSeeder extends Seeder
{
    /**
     * Seed uploaded session slots for live hosts.
     */
    public function run(): void
    {
        $this->command->info('Seeding Session Slots (Uploaded Sessions)...');

        // Get all live hosts
        $liveHosts = User::where('role', 'live_host')->where('status', 'active')->get();

        if ($liveHosts->isEmpty()) {
            $this->command->warn('No live hosts found. Please run LiveHostSeeder first.');
            return;
        }

        // Get platform accounts
        $platformAccounts = PlatformAccount::active()->get();

        if ($platformAccounts->isEmpty()) {
            $this->command->warn('No platform accounts found. Please run LiveHostSeeder first.');
            return;
        }

        // Create placeholder image if it doesn't exist
        $placeholderPath = $this->createPlaceholderImage();

        $remarks = [
            'Good session today, many viewers engaged!',
            'Had some technical issues at the start but recovered well.',
            'Great sales conversion, customers were very interested.',
            'Slow start but picked up momentum in the second hour.',
            'Record breaking session! Best performance this month.',
            'Normal session, steady engagement throughout.',
            'New product launch went smoothly.',
            'Collaborated with another host, doubled the audience.',
            null, // Some sessions without remarks
            null,
        ];

        $createdCount = 0;

        foreach ($liveHosts as $host) {
            // Get or create platform accounts for this host
            $hostAccounts = $platformAccounts->where('user_id', $host->id);

            if ($hostAccounts->isEmpty()) {
                // Use random platform accounts if host doesn't have specific ones
                $hostAccounts = $platformAccounts->random(min(2, $platformAccounts->count()));
            }

            // Create 5-10 uploaded sessions per host
            $numSessions = rand(5, 10);

            for ($i = 0; $i < $numSessions; $i++) {
                $account = $hostAccounts->random();

                // Random date in the past 30 days
                $sessionDate = now()->subDays(rand(1, 30));

                // Random time slot
                $startHour = rand(6, 21);
                $startMinute = [0, 30][rand(0, 1)];
                $durationMinutes = rand(60, 180); // 1-3 hours

                $scheduledStart = $sessionDate->copy()->setTime($startHour, $startMinute);

                // Actual times might differ slightly from scheduled
                $actualStartOffset = rand(-5, 10); // Start up to 5 min early or 10 min late
                $actualStart = $scheduledStart->copy()->addMinutes($actualStartOffset);
                $actualEnd = $actualStart->copy()->addMinutes($durationMinutes);

                // Find or create a matching schedule
                $schedule = LiveSchedule::where('platform_account_id', $account->id)
                    ->where('day_of_week', $sessionDate->dayOfWeek)
                    ->first();

                if (!$schedule) {
                    $schedule = LiveSchedule::create([
                        'platform_account_id' => $account->id,
                        'day_of_week' => $sessionDate->dayOfWeek,
                        'start_time' => sprintf('%02d:%02d:00', $startHour, $startMinute),
                        'end_time' => sprintf('%02d:%02d:00', min($startHour + 2, 23), $startMinute),
                        'is_recurring' => true,
                        'is_active' => true,
                        'live_host_id' => $host->id,
                    ]);
                }

                // Randomly decide if this session is already uploaded or pending
                $isUploaded = rand(0, 1) === 1;

                // Create the session
                $session = LiveSession::create([
                    'platform_account_id' => $account->id,
                    'live_schedule_id' => $schedule->id,
                    'live_host_id' => $host->id,
                    'title' => $this->generateSessionTitle($account, $sessionDate),
                    'description' => "Live streaming session on {$sessionDate->format('l, F j, Y')}",
                    'status' => 'ended',
                    'scheduled_start_at' => $scheduledStart,
                    'actual_start_at' => $isUploaded ? $actualStart : null,
                    'actual_end_at' => $isUploaded ? $actualEnd : null,
                    'duration_minutes' => $isUploaded ? $durationMinutes : null,
                    'image_path' => $isUploaded ? $placeholderPath : null,
                    'remarks' => $isUploaded ? $remarks[array_rand($remarks)] : null,
                    'uploaded_at' => $isUploaded ? $sessionDate->copy()->addHours(rand(1, 24)) : null,
                    'uploaded_by' => $isUploaded ? $host->id : null,
                ]);

                $createdCount++;
                $status = $isUploaded ? 'uploaded' : 'pending';
                $this->command->info("  Created session: {$session->title} by {$host->name} [{$status}]");
            }
        }

        $this->command->newLine();
        $this->command->info("Session Slots seeding completed!");
        $this->command->info("  - Created {$createdCount} total sessions");
        $this->command->info("  - Uploaded sessions: " . LiveSession::whereNotNull('uploaded_at')->count());
        $this->command->info("  - Pending upload: " . LiveSession::whereNull('uploaded_at')->where('status', 'ended')->count());
    }

    /**
     * Generate a session title based on platform and date.
     */
    protected function generateSessionTitle(PlatformAccount $account, $date): string
    {
        $titles = [
            'Morning Live Sale',
            'Afternoon Shopping Session',
            'Evening Live Stream',
            'Weekend Special Sale',
            'Flash Sale Live',
            'New Arrivals Showcase',
            'Customer Q&A Session',
            'Product Demo Live',
            'Clearance Sale Live',
            'Limited Time Offers',
        ];

        $dayType = $date->isWeekend() ? 'Weekend' : $date->format('l');
        $timeOfDay = $date->hour < 12 ? 'Morning' : ($date->hour < 17 ? 'Afternoon' : 'Evening');

        return "{$timeOfDay} Live - {$account->name}";
    }

    /**
     * Create a placeholder image for sessions.
     */
    protected function createPlaceholderImage(): string
    {
        $directory = 'live-sessions';
        $filename = 'placeholder-session.png';
        $path = "{$directory}/{$filename}";

        // Check if already exists
        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        // Create directory if not exists
        Storage::disk('public')->makeDirectory($directory);

        // Create a simple placeholder image using GD
        if (function_exists('imagecreatetruecolor')) {
            $width = 800;
            $height = 450;
            $image = imagecreatetruecolor($width, $height);

            // Colors
            $bgColor = imagecolorallocate($image, 59, 130, 246); // Blue
            $textColor = imagecolorallocate($image, 255, 255, 255); // White

            // Fill background
            imagefill($image, 0, 0, $bgColor);

            // Add text
            $text = 'Live Session Screenshot';
            $fontSize = 5;
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $textX = ($width - $textWidth) / 2;
            $textY = ($height - imagefontheight($fontSize)) / 2;
            imagestring($image, $fontSize, $textX, $textY, $text, $textColor);

            // Save image
            $tempPath = sys_get_temp_dir() . '/' . $filename;
            imagepng($image, $tempPath);
            imagedestroy($image);

            // Move to storage
            Storage::disk('public')->put($path, file_get_contents($tempPath));
            unlink($tempPath);

            $this->command->info("Created placeholder image at: {$path}");
        } else {
            // GD not available, just note it
            $this->command->warn("GD library not available. Sessions will have null image_path.");
            return '';
        }

        return $path;
    }
}
