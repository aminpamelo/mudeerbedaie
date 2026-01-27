<?php

namespace Database\Seeders;

use App\Models\BroadcastLog;
use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use Illuminate\Database\Seeder;

class ClearNotificationDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Clearing notification data...');

        // Count before clearing
        $notificationLogCount = NotificationLog::count();
        $scheduledNotificationCount = ScheduledNotification::count();
        $broadcastLogCount = BroadcastLog::count();

        $this->command->info("Found:");
        $this->command->info("  - NotificationLog: {$notificationLogCount}");
        $this->command->info("  - ScheduledNotification: {$scheduledNotificationCount}");
        $this->command->info("  - BroadcastLog: {$broadcastLogCount}");

        // Clear all notification logs
        NotificationLog::truncate();
        $this->command->info('✓ Cleared NotificationLog table');

        // Clear all scheduled notifications
        ScheduledNotification::truncate();
        $this->command->info('✓ Cleared ScheduledNotification table');

        // Clear all broadcast logs
        BroadcastLog::truncate();
        $this->command->info('✓ Cleared BroadcastLog table');

        $this->command->info('');
        $this->command->info('All notification data has been cleared!');
    }
}
