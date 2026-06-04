<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReconcileStripeOrders extends Command
{
    protected $signature = 'stripe:reconcile-orders
                            {--subscription= : Reconcile a single Stripe subscription id}
                            {--enrollment= : Reconcile a single enrollment id}
                            {--limit=100 : Max invoices to scan per subscription}
                            {--dry-run : Preview missing orders without creating them}';

    protected $description = 'Backfill local Order rows for paid Stripe invoices that never reached us via webhook';

    public function handle(StripeService $stripeService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $enrollments = $this->resolveEnrollments();

        if ($enrollments->isEmpty()) {
            $this->warn('No matching Stripe-backed enrollments found.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Reconciling '.$enrollments->count().' enrollment(s)...');

        $rows = [];
        $created = 0;
        $skipped = 0;

        foreach ($enrollments as $enrollment) {
            try {
                $result = $stripeService->reconcileSubscriptionOrders($enrollment, $dryRun, $limit);
            } catch (\Exception $e) {
                $this->error("Failed to reconcile {$enrollment->stripe_subscription_id}: {$e->getMessage()}");
                Log::error('Reconcile: failed to reconcile subscription', [
                    'enrollment_id' => $enrollment->id,
                    'subscription_id' => $enrollment->stripe_subscription_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($result['rows'] as $row) {
                array_unshift($row, $enrollment->id);
                $rows[] = $row;
            }

            $created += $result['created'];
            $skipped += $result['skipped'];
        }

        if (! empty($rows)) {
            $this->table(['Enrollment', 'Invoice', 'Period', 'Amount', 'Action'], $rows);
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Done. {$created} missing order(s), {$skipped} already present/unpaid.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Enrollment>
     */
    private function resolveEnrollments(): Collection
    {
        $query = Enrollment::query()
            ->whereNotNull('stripe_subscription_id')
            ->where('stripe_subscription_id', 'not like', 'INTERNAL-%');

        if ($subscription = $this->option('subscription')) {
            $query->where('stripe_subscription_id', $subscription);
        }

        if ($enrollmentId = $this->option('enrollment')) {
            $query->where('id', $enrollmentId);
        }

        return $query->get();
    }
}
