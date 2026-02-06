<?php

namespace App\Jobs\Funnel;

use App\Models\Funnel;
use App\Models\FunnelAnalytics;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelSessionEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateFunnelAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected ?string $date = null
    ) {
        $this->date = $date ?? now()->toDateString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting funnel analytics update', ['date' => $this->date]);

        // Get all published funnels
        $funnels = Funnel::where('status', 'published')->get();

        foreach ($funnels as $funnel) {
            $this->updateFunnelAnalytics($funnel);
        }

        Log::info('Funnel analytics update completed', [
            'date' => $this->date,
            'funnels_processed' => $funnels->count(),
        ]);
    }

    /**
     * Update analytics for a single funnel.
     */
    protected function updateFunnelAnalytics(Funnel $funnel): void
    {
        // Update funnel-level analytics
        $this->updateAnalyticsForEntity($funnel->id, null);

        // Update step-level analytics
        foreach ($funnel->steps as $step) {
            $this->updateAnalyticsForEntity($funnel->id, $step->id);
        }
    }

    /**
     * Update analytics for a funnel or step.
     */
    protected function updateAnalyticsForEntity(int $funnelId, ?int $stepId): void
    {
        $analytics = FunnelAnalytics::getOrCreateForToday($funnelId, $stepId);

        // Calculate unique visitors
        $uniqueVisitors = FunnelSession::query()
            ->where('funnel_id', $funnelId)
            ->whereDate('created_at', $this->date)
            ->count();

        // Calculate page views
        $pageViewsQuery = FunnelSessionEvent::query()
            ->whereHas('session', fn ($q) => $q->where('funnel_id', $funnelId))
            ->where('event_type', 'page_view')
            ->whereDate('created_at', $this->date);

        if ($stepId) {
            $pageViewsQuery->where('step_id', $stepId);
        }

        $pageviews = $pageViewsQuery->count();

        // Calculate conversions and revenue
        $ordersQuery = FunnelOrder::query()
            ->where('funnel_id', $funnelId)
            ->whereDate('created_at', $this->date);

        if ($stepId) {
            $ordersQuery->where('step_id', $stepId);
        }

        $orders = $ordersQuery->get();
        $conversions = $orders->count();
        $revenue = $orders->sum('funnel_revenue');

        // Calculate average time on page (from events)
        $avgTimeQuery = FunnelSessionEvent::query()
            ->whereHas('session', fn ($q) => $q->where('funnel_id', $funnelId))
            ->where('event_type', 'time_on_page')
            ->whereDate('created_at', $this->date);

        if ($stepId) {
            $avgTimeQuery->where('step_id', $stepId);
        }

        $avgTime = $avgTimeQuery->avg(DB::raw('CAST(JSON_EXTRACT(event_data, "$.seconds") AS UNSIGNED)')) ?? 0;

        // Calculate bounce rate (sessions with only 1 page view)
        $totalSessions = FunnelSession::query()
            ->where('funnel_id', $funnelId)
            ->whereDate('created_at', $this->date)
            ->count();

        $bouncedSessions = FunnelSession::query()
            ->where('funnel_id', $funnelId)
            ->whereDate('created_at', $this->date)
            ->whereHas('events', fn ($q) => $q->where('event_type', 'page_view'), '=', 1)
            ->count();

        // Update analytics record
        $analytics->update([
            'unique_visitors' => $uniqueVisitors,
            'pageviews' => $pageviews,
            'conversions' => $conversions,
            'revenue' => $revenue,
            'avg_time_seconds' => (int) $avgTime,
            'bounce_count' => $bouncedSessions,
        ]);

        Log::debug('Updated analytics', [
            'funnel_id' => $funnelId,
            'step_id' => $stepId,
            'date' => $this->date,
            'visitors' => $uniqueVisitors,
            'conversions' => $conversions,
            'revenue' => $revenue,
        ]);
    }
}
