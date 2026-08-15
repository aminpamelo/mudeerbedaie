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

        $products = $query->orderBy('name')->paginate($request->input('per_page', 24));

        return response()->json([
            'data' => $products->map(fn (FunnelProduct $product) => [
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
            ]),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
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
            ],
        ]);
    }
}
