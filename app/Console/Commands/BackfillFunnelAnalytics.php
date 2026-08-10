<?php

namespace App\Console\Commands;

use App\Models\Funnel;
use App\Models\FunnelAnalytics;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelSessionEvent;
use Illuminate\Console\Command;

class BackfillFunnelAnalytics extends Command
{
    protected $signature = 'funnel:backfill-analytics
                            {--funnel= : Specific funnel ID to backfill}
                            {--days=90 : Number of days to backfill}
                            {--dry-run : Show what would be updated without writing}';

    protected $description = 'Backfill funnel_analytics from funnel_orders and funnel_sessions for all dates';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $funnelId = $this->option('funnel');

        $query = Funnel::query();
        if ($funnelId) {
            $query->where('id', $funnelId);
        } else {
            $query->where(function ($q) {
                $q->whereHas('orders')
                    ->orWhereHas('sessions');
            });
        }

        $funnels = $query->with('steps')->get();
        $this->info("Backfilling analytics for {$funnels->count()} funnels over {$days} days...");

        $totalUpdated = 0;

        foreach ($funnels as $funnel) {
            $updated = $this->backfillFunnel($funnel, $days, $dryRun);
            $totalUpdated += $updated;
        }

        $this->newLine();
        $this->info($dryRun
            ? "Dry run complete. Would update {$totalUpdated} analytics records."
            : "Backfill complete. Updated {$totalUpdated} analytics records."
        );

        return self::SUCCESS;
    }

    private function backfillFunnel(Funnel $funnel, int $days, bool $dryRun): int
    {
        $oldestOrder = FunnelOrder::where('funnel_id', $funnel->id)->oldest()->first();
        $oldestSession = FunnelSession::where('funnel_id', $funnel->id)->oldest()->first();

        $startDate = now()->subDays($days)->startOfDay();

        if ($oldestOrder && $oldestOrder->created_at->lt($startDate)) {
            $startDate = $oldestOrder->created_at->startOfDay();
        }
        if ($oldestSession && $oldestSession->created_at->lt($startDate)) {
            $startDate = $oldestSession->created_at->startOfDay();
        }

        $endDate = now()->endOfDay();
        $updated = 0;

        $this->line("  [{$funnel->id}] {$funnel->name}");

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();

            $updated += $this->backfillDate($funnel->id, null, $dateStr, $dryRun);

            foreach ($funnel->steps as $step) {
                $updated += $this->backfillDate($funnel->id, $step->id, $dateStr, $dryRun);
            }

            $current->addDay();
        }

        if ($updated > 0) {
            $this->info("    -> {$updated} records updated");
        }

        return $updated;
    }

    private function backfillDate(int $funnelId, ?int $stepId, string $date, bool $dryRun): int
    {
        $visitors = FunnelSession::where('funnel_id', $funnelId)
            ->whereDate('created_at', $date)
            ->count();

        $pageviewsQuery = FunnelSessionEvent::query()
            ->whereHas('session', fn ($q) => $q->where('funnel_id', $funnelId))
            ->where('event_type', 'page_view')
            ->whereDate('created_at', $date);

        if ($stepId) {
            $pageviewsQuery->where('step_id', $stepId);
        }
        $pageviews = $pageviewsQuery->count();

        $ordersQuery = FunnelOrder::where('funnel_id', $funnelId)
            ->whereDate('created_at', $date);

        if ($stepId) {
            $ordersQuery->where('step_id', $stepId);
        }
        $orders = $ordersQuery->get();
        $conversions = $orders->count();
        $revenue = (float) $orders->sum('funnel_revenue');

        if ($visitors === 0 && $pageviews === 0 && $conversions === 0) {
            return 0;
        }

        if ($dryRun) {
            if ($conversions > 0 || $visitors > 0) {
                $label = $stepId ? "step {$stepId}" : 'funnel';
                $this->line("    [{$date}] {$label}: {$visitors} visitors, {$conversions} conversions, RM {$revenue}");
            }

            return 1;
        }

        FunnelAnalytics::updateOrCreate(
            [
                'funnel_id' => $funnelId,
                'funnel_step_id' => $stepId,
                'date' => $date,
            ],
            [
                'unique_visitors' => $visitors,
                'pageviews' => $pageviews,
                'conversions' => $conversions,
                'revenue' => $revenue,
            ]
        );

        return 1;
    }
}
