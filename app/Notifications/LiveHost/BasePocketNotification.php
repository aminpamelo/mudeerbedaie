<?php

namespace App\Notifications\LiveHost;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Base class for Live Host Pocket notifications.
 *
 * Mirrors the HR BaseHrNotification web-push pattern but for the host-facing
 * Pocket PWA: the icon/badge are the violet pocket mark and the default deep
 * link is the Pocket dashboard. Subclasses declare which channels they use
 * (`database`, `mail`, `push`) and provide the title/body/deep-link.
 */
abstract class BasePocketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Channels this notification delivers on.
     *
     * @return array<int, string>
     */
    abstract protected function channels(): array;

    abstract protected function title(): string;

    abstract protected function body(): string;

    /**
     * Deep link opened when the notification is tapped.
     */
    protected function actionUrl(): string
    {
        return route('live-host.dashboard');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $map = [
            'database' => 'database',
            'mail' => 'mail',
            'push' => WebPushChannel::class,
        ];

        $result = [];
        foreach ($this->channels() as $channel) {
            if (isset($map[$channel])) {
                $result[] = $map[$channel];
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->actionUrl(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icons/pocket-192.svg')
            ->badge('/icons/pocket-192.svg')
            ->data(['url' => $this->actionUrl()]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting("Salam {$notifiable->name},")
            ->line($this->body())
            ->action('Buka Aplikasi Hos', url($this->actionUrl()));
    }
}
