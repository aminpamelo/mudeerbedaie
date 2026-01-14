<?php

namespace App\Notifications;

use App\Models\LiveSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LiveSchedule $schedule,
        public string $action = 'assigned' // 'assigned', 'removed', 'updated'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dayName = $this->schedule->day_name;
        $timeRange = $this->schedule->time_range;
        $platform = $this->schedule->platformAccount?->name ?? 'Unknown';

        $subject = match ($this->action) {
            'assigned' => 'New Schedule Assignment',
            'removed' => 'Schedule Assignment Removed',
            'updated' => 'Schedule Assignment Updated',
            default => 'Schedule Notification',
        };

        $message = match ($this->action) {
            'assigned' => "You have been assigned to a new live streaming slot.",
            'removed' => "You have been removed from a live streaming slot.",
            'updated' => "Your schedule assignment has been updated.",
            default => "There's an update to your schedule.",
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}!")
            ->line($message)
            ->line("**Platform:** {$platform}")
            ->line("**Day:** {$dayName}")
            ->line("**Time:** {$timeRange}")
            ->action('View My Schedule', route('live-host.schedule'))
            ->line('Thank you for being part of our live streaming team!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'platform_account_id' => $this->schedule->platform_account_id,
            'platform_name' => $this->schedule->platformAccount?->name,
            'day_of_week' => $this->schedule->day_of_week,
            'day_name' => $this->schedule->day_name,
            'time_range' => $this->schedule->time_range,
            'action' => $this->action,
            'message' => match ($this->action) {
                'assigned' => "You've been assigned to {$this->schedule->platformAccount?->name} on {$this->schedule->day_name} at {$this->schedule->time_range}",
                'removed' => "You've been removed from {$this->schedule->platformAccount?->name} on {$this->schedule->day_name} at {$this->schedule->time_range}",
                'updated' => "Your schedule for {$this->schedule->platformAccount?->name} on {$this->schedule->day_name} has been updated",
                default => "Schedule update for {$this->schedule->day_name}",
            },
        ];
    }
}
