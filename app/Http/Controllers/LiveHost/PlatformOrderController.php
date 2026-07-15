<?php

declare(strict_types=1);

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'shop' => ['nullable', 'integer', 'exists:platform_accounts,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'unmatched_only' => ['nullable', 'boolean'],
            'session' => ['nullable', 'integer', 'exists:live_sessions,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $query = ProductOrder::query()
            ->where('source', 'tiktok_shop')
            ->with([
                'platformAccount:id,name,platform_id',
                'matchedLiveSession:id,actual_start_at,actual_end_at,live_host_id',
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

        if ($session = $request->query('session')) {
            $query->where('matched_live_session_id', $session);
        }

        if ($from = $request->query('date_from')) {
            $query->where('paid_time', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->where('paid_time', '<=', $to.' 23:59:59');
        }

        if ($search = $request->query('search')) {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('order_number', 'like', "%{$escaped}%")
                    ->orWhere('platform_order_id', 'like', "%{$escaped}%")
                    ->orWhere('platform_order_number', 'like', "%{$escaped}%")
                    ->orWhere('guest_email', 'like', "%{$escaped}%");
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

        $filterSession = null;
        if ($sessionId = $request->query('session')) {
            $s = LiveSession::with('liveHost:id,name')->find($sessionId);
            if ($s) {
                $filterSession = [
                    'id' => $s->id,
                    'host_name' => $s->liveHost?->name,
                    'started_at' => $s->actual_start_at?->toIso8601String(),
                ];
            }
        }

        return Inertia::render('orders/Index', [
            'orders' => $orders,
            'summary' => $summary,
            'shops' => $shops,
            'filters' => $request->only(['shop', 'status', 'unmatched_only', 'session', 'date_from', 'date_to', 'search']),
            'filterSession' => $filterSession,
        ]);
    }
}
