<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $funnelFilter = $request->query('funnel');

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
            'analytics' => $this->analytics($request, is_string($funnelFilter) ? $funnelFilter : null),
            'funnelOptions' => $funnels->map(fn ($f): array => ['uuid' => $f['uuid'], 'name' => $f['name']])->values(),
            'activeFunnel' => is_string($funnelFilter) ? $funnelFilter : null,
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

    /**
     * Sales analytics computed from the fighter's real orders (funnel + manual),
     * optionally narrowed to a single funnel: today's totals, a 7-day trend, the
     * latest orders, and payment-method / payment-status breakdowns for the
     * current month.
     *
     * @return array<string, mixed>
     */
    private function analytics(Request $request, ?string $funnelUuid): array
    {
        $todayStart = now()->startOfDay();
        $weekStart = now()->subDays(6)->startOfDay();
        $monthStart = now()->startOfMonth();

        $today = $this->ownedOrders($request, $funnelUuid)->where('created_at', '>=', $todayStart);

        $weekRows = $this->ownedOrders($request, $funnelUuid)
            ->where('created_at', '>=', $weekStart)
            ->get(['created_at', 'total_amount']);

        // Seed every day in the window so the chart never has gaps. Grouping is
        // done in PHP to stay portable across SQLite (dev) and MySQL (prod).
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $days[$day->format('Y-m-d')] = [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('D'),
                'orders' => 0,
                'revenue' => 0.0,
            ];
        }
        foreach ($weekRows as $row) {
            $key = optional($row->created_at)->format('Y-m-d');
            if ($key !== null && isset($days[$key])) {
                $days[$key]['orders']++;
                $days[$key]['revenue'] += (float) $row->total_amount;
            }
        }
        $week = array_values($days);

        $recent = $this->ownedOrders($request, $funnelUuid)
            ->with('customer')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (ProductOrder $o): array => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'customer' => $o->customer_name ?: $o->customer?->name ?: 'Guest',
                'total' => (float) $o->total_amount,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'created_at' => optional($o->created_at)->toIso8601String(),
            ])
            ->values();

        return [
            'today' => [
                'orders' => (clone $today)->count(),
                'revenue' => (float) (clone $today)->sum('total_amount'),
            ],
            'week' => $week,
            'weekTotals' => [
                'orders' => (int) array_sum(array_column($week, 'orders')),
                'revenue' => (float) array_sum(array_column($week, 'revenue')),
            ],
            'recent' => $recent,
            'paymentMethods' => $this->breakdown($request, $funnelUuid, 'payment_method', $monthStart, fn (?string $code): string => (new ProductOrder(['payment_method' => $code]))->payment_method_label),
            'paymentStatuses' => $this->breakdown($request, $funnelUuid, 'payment_status', $monthStart, fn (?string $code): string => ucfirst((string) ($code ?: 'unknown'))),
        ];
    }

    /**
     * Group the fighter's month-to-date orders by a column, returning ordered
     * rows of {key, label, orders, revenue}. Aggregation is pushed to the DB
     * with portable SQL (no date functions).
     *
     * @param  callable(?string): string  $label
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(Request $request, ?string $funnelUuid, string $column, Carbon $since, callable $label): array
    {
        return $this->ownedOrders($request, $funnelUuid)
            ->where('created_at', '>=', $since)
            ->selectRaw("{$column} as bucket, COUNT(*) as orders, SUM(total_amount) as revenue")
            ->groupBy($column)
            ->get()
            ->map(fn ($row): array => [
                'key' => $row->bucket ?: 'unknown',
                'label' => $label($row->bucket),
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * Base query scoped to the acting fighter's own orders (both funnel and
     * manual), optionally narrowed to a single funnel the fighter owns.
     * Non-fighters (an admin peeking) have no segment, so they match nothing.
     *
     * @return Builder<ProductOrder>
     */
    private function ownedOrders(Request $request, ?string $funnelUuid): Builder
    {
        $segmentId = $request->user()->sales_source_id;

        $query = ProductOrder::query()->when(
            $segmentId,
            fn ($q) => $q->where('sales_source_id', $segmentId),
            fn ($q) => $q->whereRaw('1 = 0'),
        );

        if ($funnelUuid !== null && $funnelUuid !== '') {
            $funnel = Funnel::query()
                ->forUser((int) $request->user()->id)
                ->where('uuid', $funnelUuid)
                ->first();

            if ($funnel === null) {
                return $query->whereRaw('1 = 0');
            }

            $orderIds = FunnelOrder::query()->forFunnel($funnel->id)->pluck('product_order_id')->filter();
            $query->whereIn('id', $orderIds);
        }

        return $query;
    }
}
