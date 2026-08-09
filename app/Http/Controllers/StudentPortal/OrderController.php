<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()->student;

        $query = Order::where('student_id', $student->id)
            ->with(['course', 'enrollment'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('course'), fn ($q, $c) => $q->where('course_id', $c))
            ->orderByDesc('created_at');

        $orders = $query->paginate(10)->withQueryString();

        $orders->getCollection()->transform(fn (Order $o) => [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'course_name' => $o->course?->name,
            'status' => $o->status,
            'status_label' => $o->status_label,
            'amount' => $o->amount,
            'formatted_amount' => $o->formatted_amount,
            'period_description' => $o->getPeriodDescription(),
            'created_at' => $o->created_at->format('M j, Y'),
            'paid_at' => $o->paid_at?->format('M j, Y'),
            'is_paid' => $o->isPaid(),
            'is_failed' => $o->isFailed(),
            'is_pending' => $o->isPending(),
            'failure_message' => $o->failure_reason['failure_message'] ?? null,
            'has_active_subscription' => $o->enrollment?->hasActiveSubscription() ?? false,
        ]);

        $totalPaid = Order::where('student_id', $student->id)->paid()->sum('amount');
        $totalOrders = Order::where('student_id', $student->id)->count();

        $courses = Order::where('student_id', $student->id)
            ->with('course')
            ->get()
            ->pluck('course')
            ->filter()
            ->unique('id')
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values();

        return Inertia::render('Orders', [
            'orders' => $orders,
            'totalPaid' => $totalPaid,
            'totalOrders' => $totalOrders,
            'courses' => $courses,
            'orderStatuses' => Order::getStatuses(),
            'filters' => [
                'status' => $request->input('status', ''),
                'course' => $request->input('course', ''),
            ],
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $student = $request->user()->student;

        if ($order->student_id !== $student->id) {
            abort(403, 'You can only view your own orders.');
        }

        $order->load(['student.user', 'course', 'enrollment', 'items']);

        return Inertia::render('OrderShow', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'amount' => $order->amount,
                'formatted_amount' => $order->formatted_amount,
                'currency' => strtoupper($order->currency),
                'period_description' => $order->getPeriodDescription(),
                'billing_reason_label' => $order->billing_reason_label,
                'created_at' => $order->created_at->format('M j, Y g:i A'),
                'paid_at' => $order->paid_at?->format('M j, Y g:i A'),
                'failed_at' => $order->failed_at?->format('M j, Y g:i A'),
                'is_paid' => $order->isPaid(),
                'is_failed' => $order->isFailed(),
                'is_pending' => $order->isPending(),
                'failure_reason' => $order->failure_reason,
                'stripe_charge_id' => $order->stripe_charge_id,
                'items' => $order->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'formatted_unit_price' => $i->formatted_unit_price,
                    'formatted_total_price' => $i->formatted_total_price,
                ]),
                'course' => $order->course ? [
                    'name' => $order->course->name,
                    'description' => $order->course->description ? \Str::limit($order->course->description, 100) : null,
                ] : null,
                'enrollment' => $order->enrollment ? [
                    'status' => ucfirst($order->enrollment->status),
                    'subscription_status_label' => $order->enrollment->subscription_status ? $order->enrollment->getSubscriptionStatusLabel() : null,
                    'enrollment_date' => $order->enrollment->enrollment_date?->format('M j, Y'),
                    'has_active_subscription' => $order->enrollment->hasActiveSubscription(),
                ] : null,
            ],
        ]);
    }

    public function receipt(Request $request, Order $order): Response
    {
        $student = $request->user()->student;

        if ($order->student_id !== $student->id) {
            abort(403, 'You can only view your own receipts.');
        }

        if (! $order->isPaid()) {
            abort(404, 'Receipt not available for unpaid orders.');
        }

        $order->load(['student.user', 'course', 'items']);

        return Inertia::render('OrderReceipt', [
            'appName' => config('app.name'),
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status_label' => $order->status_label,
                'formatted_amount' => $order->formatted_amount,
                'currency' => strtoupper($order->currency),
                'period_description' => $order->getPeriodDescription(),
                'paid_at' => $order->paid_at?->format('M j, Y g:i A'),
                'stripe_charge_id' => $order->stripe_charge_id,
                'student_name' => $order->student->user->name,
                'student_email' => $order->student->user->email,
                'student_id' => $order->student->student_id,
                'course_name' => $order->course?->name,
                'course_description' => $order->course?->description ? \Str::limit($order->course->description, 100) : null,
                'items' => $order->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'formatted_unit_price' => $i->formatted_unit_price,
                    'formatted_total_price' => $i->formatted_total_price,
                ]),
            ],
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }
}
