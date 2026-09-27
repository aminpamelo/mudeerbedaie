<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotFlow;
use App\Models\ProductOrder;
use App\Services\Cekbot\CekbotBotService;
use App\Services\Cekbot\CekbotPaymentProof;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders created by the WhatsApp bot (Cekbot flows — AI or menu driven).
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'flow' => $request->integer('flow') ?: null,
            'payment' => $request->query('payment') ?: null,
            'status' => $request->query('status') ?: null,
        ];

        $base = ProductOrder::query()->where('source', 'whatsapp_bot');

        $orders = (clone $base)
            ->with('items:id,order_id,product_name,quantity_ordered')
            ->when($filters['search'] !== '', function (Builder $q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn (Builder $w) => $w->where('order_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term));
            })
            ->when($filters['flow'], fn (Builder $q, int $flowId) => $q->where('metadata->cekbot_flow_id', $flowId))
            ->when($filters['payment'], fn (Builder $q, string $method) => $q->where('payment_method', $method))
            ->when($filters['status'] === 'proof', fn (Builder $q) => $q->where('payment_status', 'pending')->whereNotNull('metadata->payment_proof_submitted_at'))
            ->when($filters['status'] && $filters['status'] !== 'proof', fn (Builder $q) => $q->where('payment_status', $filters['status']))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ProductOrder $order) => $this->shapeOrder($order));

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'flows' => CekbotFlow::query()->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => (clone $base)->count(),
                'revenue' => (float) (clone $base)->where('status', '!=', 'cancelled')->sum('total_amount'),
                'pending_payment' => (clone $base)->where('payment_status', 'pending')->count(),
                'awaiting_confirmation' => (clone $base)->where('payment_status', 'pending')->whereNotNull('metadata->payment_proof_submitted_at')->count(),
                'today' => (clone $base)->where('created_at', '>=', now()->startOfDay())->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeOrder(ProductOrder $order): array
    {
        $metadata = $order->metadata ?? [];

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'created_at' => $order->created_at?->toIso8601String(),
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'chat_id' => $metadata['chat_id'] ?? null,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity_ordered,
            ])->all(),
            'total' => (float) $order->total_amount,
            'currency' => $order->currency ?: 'RM',
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'status' => $order->status,
            'flow_name' => $metadata['cekbot_flow_name'] ?? null,
            'driver' => $metadata['driver'] ?? null,
            'address' => $order->shipping_address['full_address'] ?? null,
            'proof_submitted' => CekbotPaymentProof::submitted($order),
            'receipt_url' => $order->receipt_attachment_url,
            'url' => route('admin.orders.show', $order),
        ];
    }

    /**
     * Team confirms a bot transfer order's payment after checking the receipt.
     */
    public function confirmPayment(Request $request, ProductOrder $order, CekbotPaymentProof $proofs, CekbotBotService $bot): RedirectResponse
    {
        abort_unless($order->source === 'whatsapp_bot', 404);

        if ($order->payment_status === 'paid') {
            return back()->with('success', 'Pesanan ini sudah disahkan.');
        }

        $proofs->confirm($order, $request->user(), $bot);

        return back()->with('success', 'Bayaran disahkan & pelanggan dimaklumkan di WhatsApp.');
    }
}
