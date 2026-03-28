<?php

namespace App\Notifications\Hr;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

abstract class BaseHrNotification extends Notification
{
    /**
     * Define which channels this notification uses.
     * Subclasses override this to customize.
     *
     * @return array<string>
     */
    abstract protected function channels(): array;

    /**
     * The notification title for push/in-app.
     */
    abstract protected function title(): string;

    /**
     * The notification body message.
     */
    abstract protected function body(): string;

    /**
     * The URL to navigate to when notification is clicked.
     */
    abstract protected function actionUrl(): string;

    /**
     * The notification icon name (for in-app display).
     */
    protected function icon(): string
    {
        return 'bell';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channelMap = [
            'database' => 'database',
            'mail' => 'mail',
            'push' => WebPushChannel::class,
        ];

        $result = [];
        foreach ($this->channels() as $channel) {
            if (isset($channelMap[$channel])) {
                $result[] = $channelMap[$channel];
            }
        }

        return $result;
    }

    /**
     * Get the array representation (for database channel).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->actionUrl(),
            'icon' => $this->icon(),
        ];
    }

    /**
     * Get the web push representation.
     */
    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icons/hr-192.png')
            ->badge('/icons/hr-192.png')
            ->data(['url' => $this->actionUrl()]);
    }

    /**
     * Get the mail representation.
     * Subclasses can override for custom email layouts.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting("Hello {$notifiable->name}!")
            ->line($this->body())
            ->action('View Details', url($this->actionUrl()))
            ->line('This is an automated notification from Mudeer HR.');
    }
}
