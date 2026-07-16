<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\ProductOrder;
use App\Services\LiveHost\Tiktok\OrderRefundReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileRefundAdjustments extends Command
{
    /**
     * @var string
     */
    protected $signature = 'livehost:reconcile-refunds
        {--session= : Limit to a single live_session_id}
        {--dry-run : Report what would change without writing}';

    /**
     * @var string
     */
    protected $description = 'Backfill auto-deduct of refunded/cancelled TikTok orders: approve stuck proposals and record missing ones, then recompute each session Net GMV.';

    public function handle(OrderRefundReconciler $reconciler): int
    {
        $sessionId = $this->option('session') ? (int) $this->option('session') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $approved = $this->approveStuckProposals($sessionId, $dryRun);
        $created = $this->recordMissing($reconciler, $sessionId, $dryRun);

        $this->newLine();
        $this->info(sprintf(
            '%s %d stuck proposal(s), recorded %d missing deduction(s).',
            $dryRun ? 'Would approve' : 'Approved',
            $approved,
            $created,
        ));

        return self::SUCCESS;
    }

    /**
     * Approve pre-existing `proposed` auto-adjustments so they finally deduct.
     * Sessions in a locked payroll period are left untouched.
     */
    private function approveStuckProposals(?int $sessionId, bool $dryRun): int
    {
        $proposals = LiveSessionGmvAdjustment::query()
            ->where('reason', 'like', 'Auto: Order #%')
            ->where('status', 'proposed')
            ->when($sessionId, fn ($q) => $q->where('live_session_id', $sessionId))
            ->get();

        $count = 0;
        $touched = [];

        foreach ($proposals as $proposal) {
            $session = LiveSession::find($proposal->live_session_id);

            if ($session === null || $session->isInLockedPayrollPeriod()) {
                continue;
            }

            $count++;
            $touched[$session->id] = $session;

            if ($dryRun) {
                continue;
            }

            $proposal->update(['status' => 'approved']);
        }

        if (! $dryRun) {
            foreach ($touched as $session) {
                $session->recalcCachedGmvAdjustment();
            }
        }

        return $count;
    }

    /**
     * Record auto-approved deductions for matched refund orders that have no
     * adjustment yet. Delegates to the reconciler's shared apply path.
     */
    private function recordMissing(OrderRefundReconciler $reconciler, ?int $sessionId, bool $dryRun): int
    {
        $orders = ProductOrder::query()
            ->where('source', 'tiktok_shop')
            ->whereNotNull('matched_live_session_id')
            ->whereIn('status', ['refunded', 'cancelled', 'returned'])
            ->when($sessionId, fn ($q) => $q->where('matched_live_session_id', $sessionId))
            ->get();

        if ($dryRun) {
            return DB::transaction(function () use ($reconciler, $orders) {
                $result = $reconciler->applyForOrders($orders);
                DB::rollBack();

                return $result['applied_count'];
            });
        }

        return $reconciler->applyForOrders($orders)['applied_count'];
    }
}
