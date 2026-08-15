<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\FunnelProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cross-funnel pages for the Funnel Studio shell: global Orders, Products,
 * and Reports. Fighters only ever see data from funnels they own;
 * admin/employee see everything (same scoping rule as FunnelController).
 */
class FunnelStudioController extends Controller
{
    /**
     * @return array<int, int>
     */
    protected function scopedFunnelIds(Request $request): array
    {
        return Funnel::query()
            ->when(
                ($user = $request->user()) && $user->isFighter(),
                fn (Builder $q) => $q->where('user_id', $user->id)
            )
            ->pluck('id')
            ->all();
    }

    /**
     * All funnel orders across every visible funnel.
     */
    public function orders(Request $request): JsonResponse
    {
        $funnelIds = $this->scopedFunnelIds($request);

        $query = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->with(['funnel:id,uuid,name', 'productOrder.customer', 'session', 'step:id,name']);

        if ($funnelId = $request->input('funnel_id')) {
            $query->where('funnel_id', $funnelId);
        }

        if ($type = $request->input('type')) {
            $query->where('order_type', $type);
        }

        if ($date = $request->input('date')) {
            $query->when($date === 'today', fn ($q) => $q->whereDate('created_at', today()))
                ->when($date === '7d', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
                ->when($date === '30d', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)));
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->whereHas('productOrder', function (Builder $q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $orders->map(fn (FunnelOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->productOrder?->order_number ?? 'N/A',
                'customer_name' => $order->productOrder?->customer_name
                    ?: $order->productOrder?->customer?->name
                    ?: 'Unknown',
                'customer_email' => $order->productOrder?->guest_email
                    ?: $order->productOrder?->customer?->email
                    ?: ($order->session?->email ?? '-'),
                'funnel_name' => $order->funnel?->name ?? '-',
                'funnel_uuid' => $order->funnel?->uuid,
                'step_name' => $order->step?->name ?? '-',
                'order_type' => $order->order_type,
                'funnel_revenue' => (float) $order->funnel_revenue,
                'order_status' => $order->productOrder?->status ?? 'unknown',
                'payment_status' => $order->productOrder?->payment_status ?? 'unknown',
                'created_at' => $order->created_at->toIso8601String(),
                'created_at_human' => $order->created_at->diffForHumans(),
            ]),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Everything about one funnel order: customer, items, payment, source.
     */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $funnelIds = $this->scopedFunnelIds($request);

        $order = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->with(['funnel:id,uuid,name', 'productOrder.customer', 'productOrder.items', 'session', 'step:id,name'])
            ->findOrFail($orderId);

        $po = $order->productOrder;

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $po?->order_number ?? 'N/A',
                'funnel_name' => $order->funnel?->name ?? '-',
                'funnel_uuid' => $order->funnel?->uuid,
                'step_name' => $order->step?->name ?? '-',
                'order_type' => $order->order_type,
                'funnel_revenue' => (float) $order->funnel_revenue,
                'created_at' => $order->created_at->toIso8601String(),
                'customer' => [
                    'name' => $po?->customer_name ?: $po?->customer?->name ?: 'Unknown',
                    'email' => $po?->guest_email ?: $po?->customer?->email ?: ($order->session?->email ?? '-'),
                ],
                'payment' => [
                    'status' => $po?->status ?? 'unknown',
                    'payment_status' => $po?->payment_status ?? 'unknown',
                    'subtotal' => (float) ($po?->subtotal ?? 0),
                    'shipping_cost' => (float) ($po?->shipping_cost ?? 0),
                    'discount_amount' => (float) ($po?->discount_amount ?? 0),
                    'coupon_code' => $po?->coupon_code,
                    'total_amount' => (float) ($po?->total_amount ?? 0),
                    'currency' => $po?->currency ?? 'MYR',
                ],
                'items' => ($po?->items ?? collect())->map(fn ($item) => [
                    'name' => $item->product_name,
                    'variant' => $item->variant_name,
                    'sku' => $item->sku,
                    'quantity' => (int) $item->quantity_ordered,
                    'unit_price' => (float) $item->unit_price,
                ])->values(),
                'source' => [
                    'utm_source' => $order->session?->utm_source ?? 'Direct',
                    'utm_campaign' => $order->session?->utm_campaign,
                    'utm_medium' => $order->session?->utm_medium,
                ],
                'funnel_flags' => [
                    'upsells_accepted' => (int) ($order->upsells_accepted ?? 0),
                    'bumps_accepted' => (int) ($order->bumps_accepted ?? 0),
                    'downsells_accepted' => (int) ($order->downsells_accepted ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Every product offered across the visible funnels.
     */
    public function products(Request $request): JsonResponse
    {
        $funnelIds = $this->scopedFunnelIds($request);

        $query = FunnelProduct::query()
            ->whereHas('step', fn (Builder $q) => $q->whereIn('funnel_id', $funnelIds))
            ->with(['step:id,funnel_id,name,type', 'step.funnel:id,uuid,name']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($funnelId = $request->input('funnel_id')) {
            $query->whereHas('step', fn (Builder $q) => $q->where('funnel_id', $funnelId));
        }

        $salesByKey = $this->productSales($funnelIds);
        $salesFor = function (FunnelProduct $product) use ($salesByKey): array {
            $funnelId = $product->step?->funnel_id;
            $key = implode(':', [$funnelId, $product->product_id ?? 0, $product->course_id ?? 0, $product->package_id ?? 0]);

            return $salesByKey[$key] ?? ['units' => 0, 'revenue' => 0.0];
        };

        $present = fn (FunnelProduct $product) => [
            'id' => $product->id,
            'name' => $product->getDisplayName(),
            'type' => $product->type,
            'image_url' => $product->getImageUrl(),
            'price' => (float) $product->getPrice(),
            'compare_at_price' => $product->compare_at_price !== null ? (float) $product->compare_at_price : null,
            'is_popular' => (bool) $product->is_popular,
            'is_recurring' => (bool) $product->is_recurring,
            'funnel_name' => $product->step?->funnel?->name ?? '-',
            'funnel_uuid' => $product->step?->funnel?->uuid,
            'step_name' => $product->step?->name ?? '-',
            'step_type' => $product->step?->type,
            'units_sold' => $salesFor($product)['units'],
            'sales_revenue' => $salesFor($product)['revenue'],
        ];

        $perPage = (int) $request->input('per_page', 24);

        // Sorting by sales needs the computed numbers, so paginate manually.
        if ($request->input('sort') === 'sales') {
            $all = $query->get()
                ->map($present)
                ->sortByDesc(fn (array $row) => [$row['sales_revenue'], $row['units_sold']])
                ->values();
            $page = max(1, (int) $request->input('page', 1));

            return response()->json([
                'data' => $all->slice(($page - 1) * $perPage, $perPage)->values(),
                'meta' => [
                    'current_page' => $page,
                    'last_page' => max(1, (int) ceil($all->count() / $perPage)),
                    'total' => $all->count(),
                ],
            ]);
        }

        $products = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $products->map($present),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Units sold + revenue per (funnel, underlying product/course/package),
     * from paid-order line items, keyed for O(1) lookup.
     *
     * @param  array<int, int>  $funnelIds
     * @return array<string, array{units: int, revenue: float}>
     */
    protected function productSales(array $funnelIds): array
    {
        return \App\Models\ProductOrderItem::query()
            ->join('product_orders', 'product_orders.id', '=', 'product_order_items.order_id')
            ->join('funnel_orders', 'funnel_orders.product_order_id', '=', 'product_orders.id')
            ->whereNull('product_orders.deleted_at')
            ->whereIn('funnel_orders.funnel_id', $funnelIds)
            ->selectRaw('funnel_orders.funnel_id, product_order_items.product_id, product_order_items.course_id, product_order_items.package_id, SUM(product_order_items.quantity_ordered) as units, SUM(product_order_items.quantity_ordered * product_order_items.unit_price) as revenue')
            ->groupBy('funnel_orders.funnel_id', 'product_order_items.product_id', 'product_order_items.course_id', 'product_order_items.package_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                implode(':', [$row->funnel_id, $row->product_id ?? 0, $row->course_id ?? 0, $row->package_id ?? 0]) => [
                    'units' => (int) $row->units,
                    'revenue' => (float) $row->revenue,
                ],
            ])
            ->all();
    }

    /**
     * Pixel health across visible published funnels, as recorded by the
     * daily funnel:pixel-health command.
     */
    public function pixelHealth(Request $request): JsonResponse
    {
        $funnels = Funnel::query()
            ->whereIn('id', $this->scopedFunnelIds($request))
            ->where('status', 'published')
            ->get(['id', 'uuid', 'name', 'settings']);

        $problems = [];
        $checkedCount = 0;
        $lastCheckedAt = null;

        foreach ($funnels as $funnel) {
            $health = data_get($funnel->settings, 'pixel_settings.health');
            if (! $health) {
                continue;
            }

            $checkedCount++;
            if (! $lastCheckedAt || ($health['checked_at'] ?? '') > $lastCheckedAt) {
                $lastCheckedAt = $health['checked_at'] ?? null;
            }

            if (($health['status'] ?? 'ok') !== 'ok') {
                $problems[] = [
                    'funnel_uuid' => $funnel->uuid,
                    'funnel_name' => $funnel->name,
                    'checked_at' => $health['checked_at'] ?? null,
                    'issues' => $health['issues'] ?? [],
                ];
            }
        }

        return response()->json([
            'data' => [
                'checked_funnels' => $checkedCount,
                'last_checked_at' => $lastCheckedAt,
                'problems' => $problems,
            ],
        ]);
    }

    /**
     * Cross-funnel report: totals, daily revenue, top funnels, type breakdown.
     */
    public function reports(Request $request): JsonResponse
    {
        $funnelIds = $this->scopedFunnelIds($request);
        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $from = now()->subDays($days)->startOfDay();

        $base = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', $from);

        $totalRevenue = (float) (clone $base)->sum('funnel_revenue');
        $totalOrders = (clone $base)->count();

        // Daily revenue series (fill missing days with zero)
        $daily = (clone $base)
            ->selectRaw('DATE(created_at) as day, SUM(funnel_revenue) as revenue, COUNT(*) as orders')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $series[] = [
                'day' => $day,
                'revenue' => (float) ($daily[$day]->revenue ?? 0),
                'orders' => (int) ($daily[$day]->orders ?? 0),
            ];
        }

        // Top funnels by revenue in the window
        $topFunnels = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', $from)
            ->selectRaw('funnel_id, SUM(funnel_revenue) as revenue, COUNT(*) as orders')
            ->groupBy('funnel_id')
            ->orderByDesc('revenue')
            ->limit(8)
            ->with('funnel:id,uuid,name,status')
            ->get()
            ->map(fn ($row) => [
                'funnel_name' => $row->funnel?->name ?? '-',
                'funnel_uuid' => $row->funnel?->uuid,
                'status' => $row->funnel?->status,
                'revenue' => (float) $row->revenue,
                'orders' => (int) $row->orders,
            ]);

        // Order type breakdown (main / upsell / downsell / bump)
        $byType = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', $from)
            ->selectRaw('order_type, SUM(funnel_revenue) as revenue, COUNT(*) as orders')
            ->groupBy('order_type')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->order_type,
                'revenue' => (float) $row->revenue,
                'orders' => (int) $row->orders,
            ]);

        // Traffic source breakdown — which ads actually bring the money.
        // Orders without a session or utm_source count as Direct.
        $bySource = FunnelOrder::query()
            ->whereIn('funnel_orders.funnel_id', $funnelIds)
            ->where('funnel_orders.created_at', '>=', $from)
            ->leftJoin('funnel_sessions', 'funnel_sessions.id', '=', 'funnel_orders.session_id')
            ->selectRaw("COALESCE(NULLIF(funnel_sessions.utm_source, ''), 'Direct') as source, SUM(funnel_orders.funnel_revenue) as revenue, COUNT(*) as orders")
            ->groupBy('source')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source,
                'revenue' => (float) $row->revenue,
                'orders' => (int) $row->orders,
            ]);

        $topCampaigns = FunnelOrder::query()
            ->whereIn('funnel_orders.funnel_id', $funnelIds)
            ->where('funnel_orders.created_at', '>=', $from)
            ->join('funnel_sessions', 'funnel_sessions.id', '=', 'funnel_orders.session_id')
            ->whereNotNull('funnel_sessions.utm_campaign')
            ->where('funnel_sessions.utm_campaign', '!=', '')
            ->selectRaw('funnel_sessions.utm_campaign as campaign, funnel_sessions.utm_source as source, SUM(funnel_orders.funnel_revenue) as revenue, COUNT(*) as orders')
            ->groupBy('campaign', 'source')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'campaign' => $row->campaign,
                'source' => $row->source,
                'revenue' => (float) $row->revenue,
                'orders' => (int) $row->orders,
            ]);

        return response()->json([
            'data' => [
                'days' => $days,
                'totals' => [
                    'revenue' => $totalRevenue,
                    'orders' => $totalOrders,
                    'avg_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
                    'funnels' => count($funnelIds),
                ],
                'daily' => $series,
                'top_funnels' => $topFunnels,
                'by_type' => $byType,
                'by_source' => $bySource,
                'top_campaigns' => $topCampaigns,
            ],
        ]);
    }
}
