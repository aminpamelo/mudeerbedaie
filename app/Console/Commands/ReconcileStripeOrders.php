<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Order;
use App\Services\StripeService;
use Carbon\Carbon;
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
                $invoices = $stripeService->listSubscriptionInvoices($enrollment->stripe_subscription_id, $limit);
            } catch (\Exception $e) {
                $this->error("Failed to list invoices for {$enrollment->stripe_subscription_id}: {$e->getMessage()}");
                Log::error('Reconcile: failed to list Stripe invoices', [
                    'enrollment_id' => $enrollment->id,
                    'subscription_id' => $enrollment->stripe_subscription_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($invoices as $data) {
                $invoiceId = $data['id'];

                if (Order::where('stripe_invoice_id', $invoiceId)->exists()) {
                    $skipped++;

                    continue;
                }

                if (($data['status'] ?? null) !== 'paid') {
                    $skipped++;

                    continue;
                }

                $period = $this->describePeriod($data);

                if ($dryRun) {
                    $rows[] = [$enrollment->id, $invoiceId, $period, number_format(($data['amount_paid'] ?? 0) / 100, 2), 'would create'];
                    $created++;

                    continue;
                }

                $order = $stripeService->createOrderFromStripeInvoice($data);

                if (! $order) {
                    $rows[] = [$enrollment->id, $invoiceId, $period, '-', 'failed'];

                    continue;
                }

                $order->markAsPaid();

                if (! empty($data['period_end'])) {
                    $enrollment->updateNextPaymentDate(Carbon::createFromTimestamp($data['period_end'])->addDay());
                }

                $rows[] = [$enrollment->id, $invoiceId, $period, number_format($order->amount, 2), 'created'];
                $created++;

                Log::info('Reconcile: created missing order from Stripe invoice', [
                    'order_id' => $order->id,
                    'enrollment_id' => $enrollment->id,
                    'stripe_invoice_id' => $invoiceId,
                ]);
            }
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

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function describePeriod(array $invoice): string
    {
        $start = $invoice['lines']['data'][0]['period']['start'] ?? $invoice['period_start'] ?? null;

        return $start ? Carbon::createFromTimestamp($start)->format('Y-m-d') : '-';
    }
}
