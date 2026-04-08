<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SendTestPushNotification extends Command
{
    protected $signature = 'push:test {email? : The user email to send to (default: admin@example.com)}';

    protected $description = 'Send a test push notification to verify Web Push is working';

    public function handle(): int
    {
        $email = $this->argument('email') ?? 'admin@example.com';
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] not found.");

            return self::FAILURE;
        }

        $subscriptions = $user->pushSubscriptions()->count();

        if ($subscriptions === 0) {
            $this->error("User [{$user->name}] has no push subscriptions.");
            $this->line('');
            $this->info('To subscribe:');
            $this->line('  1. Open /hr/notifications in your browser');
            $this->line('  2. Click "Enable Push Notifications"');
            $this->line('  3. Allow the browser notification permission');
            $this->line('  4. Run this command again');

            return self::FAILURE;
        }

        $this->info("Sending test push to [{$user->name}] ({$subscriptions} subscription(s))...");

        $user->notify(new TestPushNotification);

        $this->info('Test push notification sent! You should see it in your browser.');

        return self::SUCCESS;
    }
}

class TestPushNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Test Push Notification')
            ->body('If you see this, push notifications are working correctly!')
            ->icon('/icons/hr-192.png')
            ->badge('/icons/hr-192.png')
            ->data(['url' => '/hr/notifications']);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Test Push Notification',
            'body' => 'If you see this, push notifications are working correctly!',
            'url' => '/hr/notifications',
            'icon' => 'bell',
        ];
    }
}
