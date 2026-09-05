<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelCart;
use App\Models\FunnelOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelOrderController extends Controller
{
    /**
     * List funnel orders with filtering and pagination.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();

        $query = FunnelOrder::query()
            ->where('funnel_id', $funnel->id)
            ->with(['productOrder', 'session', 'step']);

        // Filter by order type
        if ($type = $request->input('type')) {
            $query->where('order_type', $type);
        }

        // Filter by date
        if ($date = $request->input('date')) {
            $query->when($date === 'today', fn ($q) => $q->whereDate('created_at', today()))
                ->when($date === '7d', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
                ->when($date === '30d', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)));
        }

        $orders = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $orders->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->productOrder?->order_number ?? 'N/A',
                'customer_email' => $order->productOrder?->email ?? $order->session?->email ?? 'Unknown',
                'funnel_revenue' => (float) $order->funnel_revenue,
                'formatted_revenue' => $order->getFormattedRevenue(),
                'order_type' => $order->order_type,
                'order_status' => $order->productOrder?->status ?? 'unknown',
                'step_name' => $order->step?->name ?? '-',
                'utm_source' => $order->session?->utm_source ?? 'Direct',
                'created_at' => $order->created_at->toIso8601String(),
                'created_at_human' => $order->created_at->diffForHumans(),
                'product_order_id' => $order->product_order_id,
            ]),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get order statistics for the funnel.
     */
    public function stats(string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();

        // Order counts + revenue, grouped by type in a single aggregate query.
        $orderAgg = FunnelOrder::forFunnel($funnel->id)
            ->selectRaw('order_type, COUNT(*) as orders_count, COALESCE(SUM(funnel_revenue), 0) as revenue')
            ->groupBy('order_type')
            ->get()
            ->keyBy('order_type');

        $totalOrders = (int) $orderAgg->sum('orders_count');
        $totalRevenue = (float) $orderAgg->sum('revenue');
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        $typeRow = fn (string $type): array => [
            'count' => (int) ($orderAgg->get($type)?->orders_count ?? 0),
            'revenue' => (float) ($orderAgg->get($type)?->revenue ?? 0),
        ];

        $typeBreakdown = [
            'main' => $typeRow('main'),
            'upsell' => $typeRow('upsell'),
            'downsell' => $typeRow('downsell'),
            'bump' => $typeRow('bump'),
        ];

        // Abandoned-cart recovery stats, grouped by status in a single query.
        $cartAgg = FunnelCart::forFunnel($funnel->id)
            ->whereNotNull('abandoned_at')
            ->selectRaw('recovery_status, COUNT(*) as carts_count, COALESCE(SUM(total_amount), 0) as value')
            ->groupBy('recovery_status')
            ->get()
            ->keyBy('recovery_status');

        $cartStats = [
            'total' => (int) $cartAgg->sum('carts_count'),
            'pending' => (int) ($cartAgg->get('pending')?->carts_count ?? 0),
            'sent' => (int) ($cartAgg->get('sent')?->carts_count ?? 0),
            'recovered' => (int) ($cartAgg->get('recovered')?->carts_count ?? 0),
            'expired' => (int) ($cartAgg->get('expired')?->carts_count ?? 0),
            'recoverable_value' => (float) (($cartAgg->get('pending')?->value ?? 0) + ($cartAgg->get('sent')?->value ?? 0)),
        ];

        return response()->json([
            'data' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'avg_order_value' => $avgOrderValue,
                'abandoned_count' => $cartStats['total'],
                'type_breakdown' => $typeBreakdown,
                'cart_stats' => $cartStats,
            ],
        ]);
    }

    /**
     * List abandoned carts with filtering and pagination.
     */
    public function abandonedCarts(Request $request, string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();

        $query = FunnelCart::query()
            ->where('funnel_id', $funnel->id)
            ->whereNotNull('abandoned_at')
            ->with(['session', 'step']);

        // Filter by recovery status
        if ($status = $request->input('status')) {
            $query->where('recovery_status', $status);
        }

        $carts = $query->latest('abandoned_at')->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => $carts->map(fn ($cart) => [
                'id' => $cart->id,
                'email' => $cart->email ?? 'Unknown',
                'phone' => $cart->phone,
                'total_amount' => (float) $cart->total_amount,
                'formatted_total' => $cart->getFormattedTotal(),
                'item_count' => $cart->getItemCount(),
                'recovery_status' => $cart->recovery_status,
                'recovery_emails_sent' => $cart->recovery_emails_sent,
                'step_name' => $cart->step?->name ?? '-',
                'abandoned_at' => $cart->abandoned_at?->toIso8601String(),
                'abandoned_at_human' => $cart->abandoned_at?->diffForHumans(),
                'abandonment_age_hours' => $cart->abandoned_at ? $cart->getAbandonmentAge() : null,
            ]),
            'meta' => [
                'current_page' => $carts->currentPage(),
                'last_page' => $carts->lastPage(),
                'per_page' => $carts->perPage(),
                'total' => $carts->total(),
            ],
        ]);
    }
}
