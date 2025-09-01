<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use App\Services\SettingsService;
use Illuminate\Console\Command;

class WebhookTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:test {action=status : Action to perform (status|events|failed|clean|config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test and monitor Stripe webhook functionality';

    public function __construct(private SettingsService $settingsService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        match ($action) {
            'status' => $this->showStatus(),
            'events' => $this->showEvents(),
            'failed' => $this->showFailed(),
            'clean' => $this->cleanOldEvents(),
            'config' => $this->showConfig(),
            default => $this->showHelp()
        };

        return 0;
    }

    private function showStatus(): void
    {
        $this->info('🎯 Stripe Webhook Status');
        $this->line('========================');

        // Configuration status
        $webhookSecret = $this->settingsService->get('stripe_webhook_secret');
        $secretStatus = $webhookSecret ? '✅ Configured' : '❌ Not configured';

        $stripeSecret = $this->settingsService->get('stripe_secret_key');
        $stripeStatus = $stripeSecret ? '✅ Configured' : '❌ Not configured';

        $this->line("Webhook Secret: $secretStatus");
        $this->line("Stripe Secret: $stripeStatus");
        $this->line('Payment Mode: '.$this->settingsService->get('payment_mode', 'Not set'));

        // Event statistics
        $totalEvents = WebhookEvent::count();
        $processedEvents = WebhookEvent::processed()->count();
        $failedEvents = WebhookEvent::failed()->count();
        $pendingEvents = WebhookEvent::pending()->count();

        $this->newLine();
        $this->line('📊 Event Statistics:');
        $this->line("Total Events: $totalEvents");
        $this->line("Processed: $processedEvents");
        $this->line("Failed: $failedEvents");
        $this->line("Pending: $pendingEvents");

        // Webhook URL
        $this->newLine();
        $this->line('🔗 Webhook URL: '.url('/stripe/webhook'));

        // Recent activity
        $recentEvent = WebhookEvent::latest()->first();
        if ($recentEvent) {
            $this->newLine();
            $this->line('📅 Last Event: '.$recentEvent->type.' at '.$recentEvent->created_at);
        }
    }

    private function showEvents(): void
    {
        $this->info('📋 Recent Webhook Events');
        $this->line('========================');

        $events = WebhookEvent::latest()->limit(20)->get();

        if ($events->isEmpty()) {
            $this->warn('No webhook events found.');

            return;
        }

        $headers = ['Status', 'Type', 'Event ID', 'Created', 'Error'];
        $rows = [];

        foreach ($events as $event) {
            $status = $event->processed ? '✅' : ($event->error_message ? '❌' : '⏳');
            $rows[] = [
                $status,
                $event->type,
                substr($event->stripe_event_id, 0, 20).'...',
                $event->created_at->diffForHumans(),
                $event->error_message ? substr($event->error_message, 0, 30).'...' : '-',
            ];
        }

        $this->table($headers, $rows);
    }

    private function showFailed(): void
    {
        $this->error('❌ Failed Webhook Events');
        $this->line('========================');

        $failed = WebhookEvent::failed()->get();

        if ($failed->isEmpty()) {
            $this->info('✅ No failed webhook events!');

            return;
        }

        foreach ($failed as $event) {
            $this->line("🔴 {$event->type} ({$event->stripe_event_id})");
            $this->line("   Retries: {$event->retry_count}");
            $this->line("   Error: {$event->error_message}");
            $this->line("   Created: {$event->created_at}");
            $this->newLine();
        }

        if ($this->confirm('Retry failed webhooks that can be retried?')) {
            $retryable = $failed->filter->canRetry();
            $this->line("Retrying {$retryable->count()} webhooks...");
            // Note: You would implement retry logic here
            $this->info('Retry functionality would be implemented here.');
        }
    }

    private function cleanOldEvents(): void
    {
        $this->info('🧹 Cleaning Old Webhook Events');
        $this->line('===============================');

        $count = WebhookEvent::where('processed', true)
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        if ($count === 0) {
            $this->info('No old webhook events to clean.');

            return;
        }

        if ($this->confirm("Delete $count processed webhook events older than 30 days?")) {
            WebhookEvent::where('processed', true)
                ->where('created_at', '<', now()->subDays(30))
                ->delete();

            $this->info("✅ Deleted $count old webhook events.");
        }
    }

    private function showConfig(): void
    {
        $this->info('⚙️  Stripe Webhook Configuration');
        $this->line('=================================');

        $settings = [
            'Webhook Secret' => $this->settingsService->get('stripe_webhook_secret') ? 'whsec_***' : 'Not configured',
            'Stripe Secret Key' => $this->settingsService->get('stripe_secret_key') ? 'sk_***' : 'Not configured',
            'Stripe Publishable Key' => $this->settingsService->get('stripe_publishable_key') ? 'pk_***' : 'Not configured',
            'Payment Mode' => $this->settingsService->get('payment_mode', 'Not set'),
            'Currency' => $this->settingsService->get('currency', 'Not set'),
            'Stripe Payments Enabled' => $this->settingsService->get('enable_stripe_payments') ? 'Yes' : 'No',
        ];

        foreach ($settings as $key => $value) {
            $this->line("$key: $value");
        }

        $this->newLine();
        $this->line('🔗 Webhook URL: '.url('/stripe/webhook'));
        $this->line('📝 Configure at: '.url('/admin/settings/payment'));

        $this->newLine();
        $this->info('💡 Testing Instructions:');
        $this->line('1. Install Stripe CLI: brew install stripe/stripe-cli/stripe');
        $this->line('2. Login: stripe login');
        $this->line('3. Forward webhooks: stripe listen --forward-to localhost:8000/stripe/webhook');
        $this->line('4. Trigger events: stripe trigger customer.updated');
    }

    private function showHelp(): void
    {
        $this->info('🎯 Stripe Webhook Testing Commands');
        $this->line('==================================');
        $this->newLine();

        $this->line('Available actions:');
        $this->line('  status   - Show webhook system status (default)');
        $this->line('  events   - Show recent webhook events');
        $this->line('  failed   - Show failed webhook events');
        $this->line('  clean    - Clean old processed webhook events');
        $this->line('  config   - Show webhook configuration');
        $this->newLine();

        $this->line('Examples:');
        $this->line('  php artisan webhook:test');
        $this->line('  php artisan webhook:test events');
        $this->line('  php artisan webhook:test failed');
        $this->line('  php artisan webhook:test config');
    }
}
