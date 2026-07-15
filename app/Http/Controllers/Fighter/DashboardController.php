<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Fighter home — lists only the fighter's own funnels with quick stats and
     * this-month performance summary.
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        $funnels = Funnel::query()
            ->forUser($userId)
            ->withCount(['steps', 'sessions', 'orders'])
            ->latest()
            ->get()
            ->map(fn (Funnel $funnel): array => [
                'uuid' => $funnel->uuid,
                'name' => $funnel->name,
                'status' => $funnel->status,
                'slug' => $funnel->slug,
                'type' => $funnel->type,
                'steps_count' => $funnel->steps_count,
                'sessions_count' => $funnel->sessions_count,
                'orders_count' => $funnel->orders_count,
                'revenue' => (float) $funnel->getTotalRevenue(),
                'conversion_rate' => $funnel->getConversionRate(),
                'public_url' => $funnel->getPublicUrl(),
                'builder_url' => $funnel->getBuilderUrl(),
                'created_at' => optional($funnel->created_at)->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'funnels' => $funnels,
            'stats' => $this->monthlySummary($userId),
            'builderCreateUrl' => url('/funnel-builder'),
        ]);
    }

    /**
     * Current-month headline numbers across all of the fighter's funnels.
     *
     * @return array{funnelsTotal: int, funnelsPublished: int, ordersThisMonth: int, revenueThisMonth: float}
     */
    private function monthlySummary(int $userId): array
    {
        $funnelIds = Funnel::query()->forUser($userId)->pluck('id');

        $monthOrders = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', now()->startOfMonth());

        return [
            'funnelsTotal' => $funnelIds->count(),
            'funnelsPublished' => Funnel::query()->forUser($userId)->published()->count(),
            // Count main purchases (upsells/bumps are separate rows) so the
            // headline matches the monthly performance report.
            'ordersThisMonth' => (clone $monthOrders)->main()->count(),
            'revenueThisMonth' => (float) (clone $monthOrders)->sum('funnel_revenue'),
        ];
    }
}
