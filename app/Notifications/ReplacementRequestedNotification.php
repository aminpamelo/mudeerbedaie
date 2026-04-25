<?php

namespace App\Notifications;

use App\Models\SessionReplacementRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReplacementRequestedNotification extends Notification implements ShouldQueue
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
        $host = $req->originalHost?->name ?? 'Live host';
        $platform = $req->assignment?->platformAccount?->name ?? 'Platform';
        $dayName = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'][$req->assignment?->day_of_week ?? 0];
        $time = sprintf('%s – %s',
            substr((string) ($req->assignment?->timeSlot?->start_time ?? ''), 0, 5),
            substr((string) ($req->assignment?->timeSlot?->end_time ?? ''), 0, 5)
        );
        $when = $req->scope === SessionReplacementRequest::SCOPE_ONE_DATE
            ? $req->target_date?->format('Y-m-d').' (sekali sahaja)'
            : 'Mulai segera (penggantian kekal)';

        $reasonLabel = [
            'sick' => 'Sakit',
            'family' => 'Kecemasan keluarga',
            'personal' => 'Urusan peribadi',
            'other' => 'Lain-lain',
        ][$req->reason_category] ?? $req->reason_category;

        return (new MailMessage)
            ->subject("Permohonan Ganti Slot — {$host} ({$dayName} ".substr((string) ($req->assignment?->timeSlot?->start_time ?? ''), 0, 5).')')
            ->greeting('Salam,')
            ->line("{$host} telah memohon penggantian untuk slot berikut:")
            ->line("**Platform:** {$platform}")
            ->line("**Slot:** {$dayName}, {$time}")
            ->line("**Tarikh:** {$when}")
            ->line("**Sebab:** {$reasonLabel}")
            ->line('**Catatan:** '.($req->reason_note ?: '—'))
            ->line('Sila tetapkan pengganti di pautan di bawah.')
            ->action('Lihat Permohonan', route('livehost.replacements.show', $req))
            ->salutation('Terima kasih, Mudeer Bedaie');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'replacement_request_id' => $this->request->id,
            'original_host_id' => $this->request->original_host_id,
            'scope' => $this->request->scope,
            'target_date' => $this->request->target_date?->toDateString(),
        ];
    }
}
