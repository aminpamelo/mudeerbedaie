<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Read-only feed of the orders that came in from the fighter's own funnels.
     *
     * Deliberately excludes customer PII (name/email/phone/address) — the
     * internal e-commerce team owns customer data and fulfilment; the fighter
     * only sees the order reference, amount, status and which funnel it came
     * from.
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $funnelIds = Funnel::query()->forUser($userId)->pluck('id');

        $page = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('order_type', 'main')
            ->with(['productOrder:id,order_number,status,payment_status,total_amount,currency,created_at', 'funnel:id,uuid,name'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Orders', [
            'orders' => [
                'data' => $page->getCollection()
                    ->filter(fn (FunnelOrder $fo) => $fo->productOrder !== null)
                    ->map(fn (FunnelOrder $fo): array => [
                        'id' => $fo->productOrder->id,
                        'order_number' => $fo->productOrder->order_number,
                        'status' => $fo->productOrder->status,
                        'payment_status' => $fo->productOrder->payment_status,
                        'total' => (float) $fo->productOrder->total_amount,
                        'currency' => $fo->productOrder->currency,
                        'funnel_name' => $fo->funnel?->name,
                        'created_at' => optional($fo->productOrder->created_at)->toIso8601String(),
                    ])
                    ->values(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }
}
