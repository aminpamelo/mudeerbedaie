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

        // Total orders and revenue
        $totalOrders = FunnelOrder::forFunnel($funnel->id)->count();
        $totalRevenue = (float) FunnelOrder::forFunnel($funnel->id)->sum('funnel_revenue');
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        // Order type breakdown
        $typeBreakdown = [
            'main' => [
                'count' => FunnelOrder::forFunnel($funnel->id)->main()->count(),
                'revenue' => (float) FunnelOrder::forFunnel($funnel->id)->main()->sum('funnel_revenue'),
            ],
            'upsell' => [
                'count' => FunnelOrder::forFunnel($funnel->id)->upsells()->count(),
                'revenue' => (float) FunnelOrder::forFunnel($funnel->id)->upsells()->sum('funnel_revenue'),
            ],
            'downsell' => [
                'count' => FunnelOrder::forFunnel($funnel->id)->downsells()->count(),
                'revenue' => (float) FunnelOrder::forFunnel($funnel->id)->downsells()->sum('funnel_revenue'),
            ],
            'bump' => [
                'count' => FunnelOrder::forFunnel($funnel->id)->bumps()->count(),
                'revenue' => (float) FunnelOrder::forFunnel($funnel->id)->bumps()->sum('funnel_revenue'),
            ],
        ];

        // Cart recovery stats
        $cartStats = [
            'total' => FunnelCart::forFunnel($funnel->id)->whereNotNull('abandoned_at')->count(),
            'pending' => FunnelCart::forFunnel($funnel->id)->whereNotNull('abandoned_at')->where('recovery_status', 'pending')->count(),
            'sent' => FunnelCart::forFunnel($funnel->id)->whereNotNull('abandoned_at')->where('recovery_status', 'sent')->count(),
            'recovered' => FunnelCart::forFunnel($funnel->id)->whereNotNull('abandoned_at')->where('recovery_status', 'recovered')->count(),
            'expired' => FunnelCart::forFunnel($funnel->id)->whereNotNull('abandoned_at')->where('recovery_status', 'expired')->count(),
            'recoverable_value' => (float) FunnelCart::forFunnel($funnel->id)
                ->whereNotNull('abandoned_at')
                ->whereIn('recovery_status', ['pending', 'sent'])
                ->sum('total_amount'),
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
