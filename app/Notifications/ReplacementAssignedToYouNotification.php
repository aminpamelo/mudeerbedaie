<?php

namespace App\Notifications;

use App\Models\SessionReplacementRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReplacementAssignedToYouNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SessionReplacementRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $req = $this->request->loadMissing(['originalHost', 'assignment.timeSlot', 'assignment.platformAccount']);
        $originalHost = $req->originalHost?->name ?? 'Live host';
        $platform = $req->assignment?->platformAccount?->name ?? 'Platform';
        $dayName = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'][$req->assignment?->day_of_week ?? 0];
        $startTime = substr((string) ($req->assignment?->timeSlot?->start_time ?? ''), 0, 5);
        $endTime = substr((string) ($req->assignment?->timeSlot?->end_time ?? ''), 0, 5);
        $time = sprintf('%s – %s', $startTime, $endTime);
        $when = $req->scope === SessionReplacementRequest::SCOPE_ONE_DATE
            ? ($req->target_date?->format('Y-m-d') ?? '—')
            : 'Mulai segera (kekal)';

        return (new MailMessage)
            ->subject("Anda Telah Ditugaskan Sebagai Pengganti — {$dayName} {$startTime}")
            ->greeting("Salam {$notifiable->name},")
            ->line('PIC telah menugaskan anda untuk menggantikan slot berikut:')
            ->line("**Platform:** {$platform}")
            ->line("**Slot:** {$dayName}, {$time}")
            ->line("**Tarikh:** {$when}")
            ->line("**Asal:** {$originalHost}")
            ->line('Komisen penuh untuk slot ini akan diberikan kepada anda seperti biasa.')
            ->line('Slot ini kini akan kelihatan dalam jadual anda.')
            ->action('Lihat Jadual Saya', route('live-host.schedule'))
            ->salutation('Sekian, terima kasih.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'replacement_request_id' => $this->request->id,
            'original_host_id' => $this->request->original_host_id,
            'replacement_host_id' => $this->request->replacement_host_id,
            'scope' => $this->request->scope,
            'target_date' => $this->request->target_date?->toDateString(),
        ];
    }
}
