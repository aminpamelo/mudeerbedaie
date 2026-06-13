<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ItTicketDeadlineReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, \App\Models\ItTicket>  $overdue
     * @param  Collection<int, \App\Models\ItTicket>  $dueToday
     */
    public function __construct(
        public Collection $overdue,
        public Collection $dueToday,
        public string $period = 'morning',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $greeting = $this->period === 'evening' ? 'Good evening' : 'Good morning';

        $mail = (new MailMessage)
            ->subject($this->summaryLine())
            ->greeting("{$greeting}, {$notifiable->name}")
            ->line('Here is a quick reminder of the IT tickets in your charge that need attention.');

        if ($this->overdue->isNotEmpty()) {
            $mail->line('**Overdue:**');
            foreach ($this->overdue as $ticket) {
                $mail->line("• {$ticket->ticket_number} — {$ticket->title} (due {$ticket->due_date?->format('M j')})");
            }
        }

        if ($this->dueToday->isNotEmpty()) {
            $mail->line('**Due today:**');
            foreach ($this->dueToday as $ticket) {
                $mail->line("• {$ticket->ticket_number} — {$ticket->title}");
            }
        }

        return $mail
            ->action('Open IT Board', route('admin.it-board.index'))
            ->line('Keep them moving — thank you!');
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('IT deadline reminder')
            ->body($this->summaryLine())
            ->data(['url' => route('admin.it-board.index')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'it_ticket_deadline_reminder',
            'period' => $this->period,
            'overdue_count' => $this->overdue->count(),
            'due_today_count' => $this->dueToday->count(),
            'overdue' => $this->overdue->map(fn ($t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'title' => $t->title,
                'due_date' => $t->due_date?->toDateString(),
            ])->values()->all(),
            'due_today' => $this->dueToday->map(fn ($t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'title' => $t->title,
            ])->values()->all(),
            'message' => $this->summaryLine(),
            'url' => route('admin.it-board.index'),
        ];
    }

    private function summaryLine(): string
    {
        $parts = [];

        if ($this->overdue->isNotEmpty()) {
            $parts[] = $this->overdue->count().' overdue';
        }

        if ($this->dueToday->isNotEmpty()) {
            $parts[] = $this->dueToday->count().' due today';
        }

        return 'IT deadlines: '.(empty($parts) ? 'nothing pending' : implode(', ', $parts));
    }
}
