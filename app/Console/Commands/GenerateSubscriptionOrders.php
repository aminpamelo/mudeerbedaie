<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateSubscriptionOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:generate-orders
                            {--days-ahead=7 : Generate orders X days before due date}
                            {--dry-run : Preview what would be generated without actually creating orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring payment orders for internal manual subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(StripeService $stripeService): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $dryRun = $this->option('dry-run');

        $this->info('🔄 Generating subscription orders...');
        $this->info("Looking for subscriptions due within {$daysAhead} days");

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No orders will be created');
        }

        // Find all active internal subscriptions
        $enrollments = Enrollment::query()
            ->where('subscription_status', 'active')
            ->where('payment_method_type', 'manual')
            ->whereNotNull('stripe_subscription_id')
            ->where(function ($query) {
                // Internal subscriptions start with INTERNAL-
                $query->where('stripe_subscription_id', 'like', 'INTERNAL-%');
            })
            ->whereNotNull('next_payment_date')
            ->where('next_payment_date', '<=', now()->addDays($daysAhead))
            ->with(['student.user', 'course.feeSettings'])
            ->get();

        $this->info("Found {$enrollments->count()} subscriptions ready for order generation");

        if ($enrollments->isEmpty()) {
            $this->info('✅ No subscriptions need orders at this time');

            return Command::SUCCESS;
        }

        $generated = 0;
        $skipped = 0;
        $errors = 0;

        $this->newLine();
        $progressBar = $this->output->createProgressBar($enrollments->count());
        $progressBar->start();

        foreach ($enrollments as $enrollment) {
            try {
                // Check if there's already a pending order for this period
                $hasPendingOrder = $enrollment->orders()
                    ->where('status', Order::STATUS_PENDING)
                    ->where('period_start', '>=', now()->subDays(30))
                    ->exists();

                if ($hasPendingOrder) {
                    $skipped++;
                    $progressBar->advance();

                    continue;
                }

                if (! $dryRun) {
                    // Generate the order
                    $order = $stripeService->generateInternalSubscriptionOrder($enrollment);

                    $this->newLine();
                    $this->line("  ✓ Generated order #{$order->order_number} for {$enrollment->student->name} - RM {$order->amount}");

                    $generated++;
                } else {
                    $this->newLine();
                    $this->line("  [DRY RUN] Would generate order for {$enrollment->student->name} - RM {$enrollment->course->feeSettings->fee_amount}");
                    $generated++;
                }

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("  ✗ Failed for enrollment #{$enrollment->id}: {$e->getMessage()}");

                Log::error('Failed to generate subscription order', [
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $errors++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('📊 Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Generated', $generated],
                ['Skipped (pending order exists)', $skipped],
                ['Errors', $errors],
            ]
        );

        if ($errors > 0) {
            $this->warn("⚠️  {$errors} orders failed to generate. Check logs for details.");

            return Command::FAILURE;
        }

        $this->info('✅ Order generation complete!');

        return Command::SUCCESS;
    }
}
