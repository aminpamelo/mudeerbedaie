<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Models\ReturnRefund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $query = ReturnRefund::query()
            ->with(['order', 'processedBy'])
            ->where('customer_id', $userId)
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at');

        $refunds = $query->paginate(10)->withQueryString();

        $refunds->getCollection()->transform(fn (ReturnRefund $r) => [
            'id' => $r->id,
            'refund_number' => $r->refund_number,
            'status' => $r->status,
            'status_label' => $r->getStatusLabel(),
            'status_color' => $r->getStatusColor(),
            'action' => $r->action,
            'action_label' => $r->getActionLabel(),
            'action_color' => $r->getActionColor(),
            'order_number' => $r->order?->order_number ?? 'N/A',
            'refund_amount' => $r->refund_amount,
            'created_at' => $r->created_at->format('M j, Y'),
            'return_date' => $r->return_date->format('M j, Y'),
            'reason' => $r->reason,
            'action_reason' => ($r->action_reason && $r->action !== 'pending') ? $r->action_reason : null,
            'action_is_approved' => $r->action === 'approved',
        ]);

        $stats = [
            'total' => ReturnRefund::where('customer_id', $userId)->count(),
            'pending' => ReturnRefund::where('customer_id', $userId)->where('action', 'pending')->count(),
            'approved' => ReturnRefund::where('customer_id', $userId)->where('action', 'approved')->count(),
            'completed' => ReturnRefund::where('customer_id', $userId)->where('status', 'refund_completed')->count(),
        ];

        return Inertia::render('RefundRequests', [
            'refunds' => $refunds,
            'stats' => $stats,
            'filters' => [
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $userId = $request->user()->id;

        $eligibleOrders = ProductOrder::where('customer_id', $userId)
            ->whereIn('status', ['delivered', 'shipped', 'completed'])
            ->whereDoesntHave('returnRefunds', fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled']))
            ->with('items')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'total_amount' => $o->total_amount,
                'status' => ucfirst($o->status),
                'created_at' => $o->created_at->format('M j, Y'),
                'item_count' => $o->items->count(),
                'items' => $o->items->take(3)->map(fn ($i) => [
                    'name' => $i->product_name,
                    'quantity' => $i->quantity_ordered,
                ]),
                'has_more_items' => $o->items->count() > 3,
                'remaining_items' => max(0, $o->items->count() - 3),
            ]);

        $preselectedOrderId = $request->input('order_id');

        return Inertia::render('RefundRequestCreate', [
            'eligibleOrders' => $eligibleOrders,
            'preselectedOrderId' => $preselectedOrderId ? (int) $preselectedOrderId : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:product_orders,id',
            'reason' => 'required|min:10|max:1000',
            'refund_amount' => 'required|numeric|min:0.01',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_holder_name' => 'required|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $order = ProductOrder::where('id', $validated['order_id'])
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        if ($validated['refund_amount'] > $order->total_amount) {
            return back()->withErrors(['refund_amount' => 'Refund amount cannot exceed the order total.']);
        }

        $refund = ReturnRefund::create([
            'refund_number' => ReturnRefund::generateRefundNumber(),
            'order_id' => $order->id,
            'customer_id' => $request->user()->id,
            'return_date' => now(),
            'reason' => $validated['reason'],
            'refund_amount' => $validated['refund_amount'],
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_holder_name' => $validated['account_holder_name'],
            'notes' => $validated['notes'] ?? null,
            'action' => 'pending',
            'status' => 'pending_review',
        ]);

        return redirect()->route('student.refund-requests.show', $refund)
            ->with('success', 'Your refund request has been submitted successfully.');
    }

    public function show(Request $request, ReturnRefund $refund): Response
    {
        if ($refund->customer_id !== $request->user()->id) {
            abort(403, 'You can only view your own refund requests.');
        }

        $refund->load(['order.items', 'processedBy']);

        return Inertia::render('RefundRequestShow', [
            'refund' => [
                'id' => $refund->id,
                'refund_number' => $refund->refund_number,
                'status' => $refund->status,
                'status_label' => $refund->getStatusLabel(),
                'status_color' => $refund->getStatusColor(),
                'action' => $refund->action,
                'action_label' => $refund->getActionLabel(),
                'action_color' => $refund->getActionColor(),
                'refund_amount' => $refund->refund_amount,
                'return_date' => $refund->return_date->format('M j, Y'),
                'reason' => $refund->reason,
                'notes' => $refund->notes,
                'bank_name' => $refund->bank_name,
                'account_number' => $refund->account_number,
                'account_holder_name' => $refund->account_holder_name,
                'tracking_number' => $refund->tracking_number,
                'action_reason' => ($refund->action_reason && $refund->action !== 'pending') ? $refund->action_reason : null,
                'processed_by_name' => $refund->processedBy?->name,
                'action_date' => $refund->action_date?->format('M j, Y g:i A'),
                'created_at' => $refund->created_at->format('M j, Y g:i A'),
                'updated_at' => $refund->updated_at->format('M j, Y g:i A'),
                'is_rejected' => $refund->status === 'rejected',
                'is_cancelled' => $refund->status === 'cancelled',
                'order' => $refund->order ? [
                    'order_number' => $refund->order->order_number,
                    'status' => ucfirst($refund->order->status),
                    'total_amount' => $refund->order->total_amount,
                    'created_at' => $refund->order->created_at->format('M j, Y'),
                    'items' => $refund->order->items->map(fn ($i) => [
                        'name' => $i->product_name,
                        'quantity' => $i->quantity_ordered,
                        'total_price' => $i->total_price,
                    ]),
                ] : null,
            ],
        ]);
    }
}
