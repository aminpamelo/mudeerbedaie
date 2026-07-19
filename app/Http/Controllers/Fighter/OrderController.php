<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Read-only feed of every order attributed to the fighter — both funnel
     * orders and manually-created (POS) orders, unified by the fighter's own
     * sales-source segment.
     *
     * Deliberately excludes customer PII (email/phone/address); the fighter
     * sees the reference, amount, status and source only.
     */
    public function index(Request $request): Response
    {
        $segmentId = $request->user()->sales_source_id;

        $page = ProductOrder::query()
            ->when(
                $segmentId,
                fn ($q) => $q->where('sales_source_id', $segmentId),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->latest()
            ->paginate(20);

        return Inertia::render('Orders', [
            'orders' => [
                'data' => $page->getCollection()->map(fn (ProductOrder $o): array => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'status' => $o->status,
                    'payment_status' => $o->payment_status,
                    'payment_method' => $o->payment_method,
                    'total' => (float) $o->total_amount,
                    'currency' => $o->currency,
                    'source_label' => $this->sourceLabel($o->source),
                    'receipt_url' => $o->receipt_attachment_url,
                    'tracking_id' => $o->tracking_id ?: null,
                    'shipping_provider' => $o->shipping_provider ?: null,
                    'created_at' => optional($o->created_at)->toIso8601String(),
                ])->values(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    /**
     * The manual "create order" form. Ensures the fighter has a sales-source
     * segment so the created order is attributed to them.
     */
    public function create(Request $request): Response
    {
        $segment = app(FighterProvisioner::class)->ensureSalesSource($request->user());

        return Inertia::render('OrderCreate', [
            'segment' => ['id' => $segment->id, 'name' => $segment->name],
            'currency' => 'MYR',
        ]);
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            'funnel' => 'Funnel',
            'pos' => 'Manual',
            null => '—',
            default => ucfirst($source),
        };
    }
}
