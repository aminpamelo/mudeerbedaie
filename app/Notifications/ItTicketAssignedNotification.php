<?php

namespace App\Notifications;

use App\Models\ItTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ItTicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ItTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;

        $mail = (new MailMessage)
            ->subject("IT Ticket assigned to you: {$ticket->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You have been put in charge of an IT ticket ({$ticket->ticket_number}).")
            ->line("**{$ticket->title}**")
            ->line('Type: '.$ticket->getTypeLabel().' · Priority: '.ucfirst($ticket->priority).' · Status: '.$ticket->getStatusLabel());

        if ($ticket->due_date) {
            $mail->line('Deadline: '.$ticket->due_date->format('l, M j, Y').' ('.$this->ticket->deadlineMeta()['label'].')');
        }

        return $mail
            ->action('View Ticket', $this->url())
            ->line('Please review and action it when you can.');
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $ticket = $this->ticket;

        return (new WebPushMessage)
            ->title('New IT ticket assigned to you')
            ->body($ticket->title)
            ->data(['url' => $this->url()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $ticket = $this->ticket;

        return [
            'kind' => 'it_ticket_assigned',
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'type' => $ticket->type?->name,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'due_date' => $ticket->due_date?->toDateString(),
            'message' => "You were assigned to {$ticket->ticket_number}: {$ticket->title}",
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('admin.it-board.index', ['ticket' => $this->ticket->id]);
    }
}
