<?php

declare(strict_types=1);

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ProductOrder::query()
            ->where('source', 'tiktok_shop')
            ->with([
                'platformAccount:id,name,platform_id',
                'matchedLiveSession:id,actual_start_at,live_host_id',
                'matchedLiveSession.liveHost:id,name',
            ]);

        if ($shop = $request->query('shop')) {
            $query->where('platform_account_id', $shop);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('unmatched_only')) {
            $query->whereNull('matched_live_session_id');
        }

        if ($from = $request->query('date_from')) {
            $query->where('paid_time', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->where('paid_time', '<=', $to.' 23:59:59');
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('platform_order_id', 'like', "%{$search}%")
                    ->orWhere('platform_order_number', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderByDesc('paid_time')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'total' => ProductOrder::where('source', 'tiktok_shop')->count(),
            'matched' => ProductOrder::where('source', 'tiktok_shop')->whereNotNull('matched_live_session_id')->count(),
            'unmatched' => ProductOrder::where('source', 'tiktok_shop')->whereNull('matched_live_session_id')->count(),
            'refunded' => ProductOrder::where('source', 'tiktok_shop')->whereIn('status', ['refunded', 'cancelled', 'returned'])->count(),
        ];

        $shops = PlatformAccount::query()
            ->whereIn('id', ProductOrder::query()->where('source', 'tiktok_shop')->select('platform_account_id'))
            ->get(['id', 'name']);

        return Inertia::render('orders/Index', [
            'orders' => $orders,
            'summary' => $summary,
            'shops' => $shops,
            'filters' => $request->only(['shop', 'status', 'unmatched_only', 'date_from', 'date_to', 'search']),
        ]);
    }
}
