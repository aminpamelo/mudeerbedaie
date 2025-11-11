<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class FixOrderAmounts extends Command
{
    protected $signature = 'orders:fix-amounts
                            {--dry-run : Preview changes without updating}';

    protected $description = 'Fix orders with zero amounts by using course fee settings';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('🔄 Finding orders with zero or invalid amounts...');

        // Find orders with amount = 0 that have an enrollment
        $orders = Order::with(['enrollment.course.feeSettings'])
            ->where(function ($query) {
                $query->where('amount', 0)
                    ->orWhereNull('amount');
            })
            ->whereNotNull('enrollment_id')
            ->get();

        $this->info("Found {$orders->count()} orders with zero/null amounts");

        if ($orders->isEmpty()) {
            $this->info('✅ No orders need fixing');

            return Command::SUCCESS;
        }

        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($orders as $order) {
            try {
                $enrollment = $order->enrollment;

                if (! $enrollment) {
                    $this->warn("  ⚠️  Order #{$order->order_number}: No enrollment found, skipping");
                    $skipped++;

                    continue;
                }

                // Determine correct fee amount
                $correctAmount = 0;

                if ($enrollment->course && $enrollment->course->feeSettings) {
                    $correctAmount = $enrollment->course->feeSettings->fee_amount;
                } elseif ($enrollment->enrollment_fee > 0) {
                    $correctAmount = $enrollment->enrollment_fee;
                }

                if ($correctAmount <= 0) {
                    $this->warn("  ⚠️  Order #{$order->order_number}: Could not determine correct amount, skipping");
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would update Order #{$order->order_number}: RM {$order->amount} → RM {$correctAmount}");
                    $fixed++;
                } else {
                    $order->update(['amount' => $correctAmount]);
                    $this->line("  ✓ Updated Order #{$order->order_number}: RM 0.00 → RM {$correctAmount}");
                    $fixed++;
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Failed to fix order #{$order->order_number}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info('📊 Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Fixed', $fixed],
                ['Skipped', $skipped],
                ['Errors', $errors],
            ]
        );

        if ($errors > 0) {
            return Command::FAILURE;
        }

        if (! $dryRun) {
            $this->info('✅ Order amounts have been fixed!');
        }

        return Command::SUCCESS;
    }
}
