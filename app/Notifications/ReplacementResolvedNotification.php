<?php

namespace App\Notifications;

use App\Models\SessionReplacementRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class ReplacementResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const RESOLUTION_ASSIGNED = 'assigned';

    public const RESOLUTION_REJECTED = 'rejected';

    public const RESOLUTION_EXPIRED = 'expired';

    public function __construct(
        public SessionReplacementRequest $request,
        public string $resolution,
    ) {
        if (! in_array($resolution, [self::RESOLUTION_ASSIGNED, self::RESOLUTION_REJECTED, self::RESOLUTION_EXPIRED], true)) {
            throw new InvalidArgumentException("Unknown resolution: {$resolution}");
        }
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $req = $this->request->loadMissing('replacementHost');
        $body = match ($this->resolution) {
            self::RESOLUTION_ASSIGNED => 'Permohonan anda telah diluluskan. Pengganti: '.($req->replacementHost?->name ?? '—').'.',
            self::RESOLUTION_REJECTED => 'Permohonan anda ditolak oleh PIC. Sebab: '.($req->rejection_reason ?? '—').'. Anda masih bertanggungjawab untuk slot ini.',
            self::RESOLUTION_EXPIRED => 'Permohonan anda telah tamat tempoh tanpa pengganti dipilih. Sila pastikan anda hadir untuk slot ini, atau hubungi PIC dengan segera.',
        };

        $subject = match ($this->resolution) {
            self::RESOLUTION_ASSIGNED => 'Permohonan Ganti Telah Diluluskan',
            self::RESOLUTION_REJECTED => 'Permohonan Ganti Ditolak',
            self::RESOLUTION_EXPIRED => 'Permohonan Ganti Tamat Tempoh',
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Salam {$notifiable->name},")
            ->line($body)
            ->action('Lihat Jadual Saya', route('live-host.schedule'))
            ->salutation('Terima kasih, Mudeer Bedaie');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'replacement_request_id' => $this->request->id,
            'resolution' => $this->resolution,
        ];
    }
}
