<?php

namespace App\Console\Commands;

use App\Models\ClassModel;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ScheduleClassNotificationsCommand extends Command
{
    protected $signature = 'notifications:schedule {--days=7 : Days ahead to schedule}';

    protected $description = 'Schedule notifications for upcoming class sessions based on timetable';

    public function handle(NotificationService $service): int
    {
        $days = (int) $this->option('days');

        // Get all active classes with active timetables and enabled notification settings
        $classes = ClassModel::with(['timetable', 'enabledNotificationSettings'])
            ->where('status', 'active')
            ->whereHas('timetable', function ($query) {
                $query->where('is_active', true);
            })
            ->whereHas('notificationSettings', function ($query) {
                $query->where('is_enabled', true)
                    ->where('notification_type', 'like', 'session_reminder_%');
            })
            ->get();

        $totalScheduled = 0;
        $classesProcessed = 0;

        foreach ($classes as $class) {
            $scheduled = $service->scheduleNotificationsFromTimetable($class, $days);
            $count = count($scheduled);
            $totalScheduled += $count;

            if ($count > 0) {
                $this->line("Scheduled {$count} notifications for class: {$class->title}");
                $classesProcessed++;
            }
        }

        $this->info("Scheduled {$totalScheduled} notifications for {$classesProcessed} classes (based on timetable).");

        return Command::SUCCESS;
    }
}
