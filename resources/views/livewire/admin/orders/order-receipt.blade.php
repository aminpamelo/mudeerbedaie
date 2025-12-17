<?php

use App\Models\ProductOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Volt\Component;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public ProductOrder $order;

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load([
            'items.product',
            'items.package',
            'items.warehouse',
            'customer',
            'addresses',
            'payments',
        ]);
    }

    public function downloadPdf()
    {
        $order = $this->order;

        $pdf = Pdf::loadView('livewire.admin.orders.order-receipt-pdf', compact('order'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
            ]);

        $filename = 'receipt-'.$order->order_number.'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}; ?>

<div class="min-h-screen bg-white print-receipt-page">
    <div class="max-w-4xl mx-auto p-8 print-receipt-content">
        <!-- Header with Action Buttons -->
        <div class="mb-8 flex items-center justify-between no-print">
            <div>
                <flux:heading size="xl">Order Receipt</flux:heading>
                <flux:text class="mt-1 text-gray-600">Order {{ $order->order_number }}</flux:text>
            </div>
            <div class="flex gap-3">
                <flux:button href="{{ route('admin.orders.show', $order) }}" variant="outline" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                        Back to Order
                    </div>
                </flux:button>
                <flux:button wire:click="downloadPdf" variant="outline">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                        Download PDF
                    </div>
                </flux:button>
                <flux:button onclick="window.print()" variant="primary">
                    <div class="flex items-center justify-center">
                        <flux:icon name="printer" class="w-4 h-4 mr-1" />
                        Print Receipt
                    </div>
                </flux:button>
            </div>
        </div>

        <!-- Receipt Content -->
        <div class="bg-white border border-gray-200 rounded-lg p-8 print:border-0 print:shadow-none">
            <!-- Company Header -->
            <div class="text-center mb-8">
                <flux:heading size="2xl" class="text-gray-800">{{ config('app.name') }}</flux:heading>
                <flux:text class="text-gray-600 mt-2">Order Receipt / Invoice</flux:text>
            </div>

            <!-- Receipt Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <!-- Left Column - Order Info -->
                <div>
                    <flux:heading size="lg" class="mb-4 text-gray-800">Order Details</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Order Number:</flux:text>
                            <flux:text class="font-semibold">{{ $order->order_number }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Order Date:</flux:text>
                            <flux:text class="font-semibold">{{ $order->order_date?->format('M j, Y') ?? $order->created_at->format('M j, Y') }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Order Status:</flux:text>
                            <flux:badge :color="match($order->status) {
                                'pending' => 'orange',
                                'processing' => 'blue',
                                'shipped' => 'purple',
                                'delivered' => 'green',
                                'cancelled' => 'red',
                                default => 'gray'
                            }">{{ ucfirst($order->status) }}</flux:badge>
                        </div>
                        @php
                            $latestPayment = $order->payments()->latest()->first();
                        @endphp
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Payment Status:</flux:text>
                            <flux:badge :color="match($latestPayment?->status) {
                                'pending' => 'orange',
                                'completed' => 'green',
                                'failed' => 'red',
                                default => 'gray'
                            }">{{ ucfirst($latestPayment?->status ?? 'Pending') }}</flux:badge>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Customer Info -->
                <div>
                    <flux:heading size="lg" class="mb-4 text-gray-800">Customer Information</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Name:</flux:text>
                            <flux:text class="font-semibold">{{ $order->getCustomerName() }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Email:</flux:text>
                            <flux:text class="font-semibold">{{ $order->getCustomerEmail() }}</flux:text>
                        </div>
                        @php
                            $phone = $order->customer_phone ?? $order->billingAddress()?->phone ?? null;
                        @endphp
                        @if($phone)
                            <div class="flex justify-between">
                                <flux:text class="text-gray-600">Phone:</flux:text>
                                <flux:text class="font-semibold">{{ $phone }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Addresses -->
            @php
                $billingAddress = $order->billingAddress();
                $shippingAddress = $order->shippingAddress();
            @endphp
            @if($billingAddress || $shippingAddress)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    @if($billingAddress)
                        <div>
                            <flux:heading size="lg" class="mb-4 text-gray-800">Billing Address</flux:heading>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p>{{ $billingAddress->first_name }} {{ $billingAddress->last_name }}</p>
                                @if($billingAddress->company)
                                    <p>{{ $billingAddress->company }}</p>
                                @endif
                                <p>{{ $billingAddress->address_line_1 }}</p>
                                @if($billingAddress->address_line_2)
                                    <p>{{ $billingAddress->address_line_2 }}</p>
                                @endif
                                <p>{{ $billingAddress->city }}, {{ $billingAddress->state }} {{ $billingAddress->postal_code }}</p>
                                <p>{{ $billingAddress->country }}</p>
                            </div>
                        </div>
                    @endif

                    @if($shippingAddress)
                        <div>
                            <flux:heading size="lg" class="mb-4 text-gray-800">Shipping Address</flux:heading>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p>{{ $shippingAddress->first_name }} {{ $shippingAddress->last_name }}</p>
                                @if($shippingAddress->company)
                                    <p>{{ $shippingAddress->company }}</p>
                                @endif
                                <p>{{ $shippingAddress->address_line_1 }}</p>
                                @if($shippingAddress->address_line_2)
                                    <p>{{ $shippingAddress->address_line_2 }}</p>
                                @endif
                                <p>{{ $shippingAddress->city }}, {{ $shippingAddress->state }} {{ $shippingAddress->postal_code }}</p>
                                <p>{{ $shippingAddress->country }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Order Items -->
            <div class="mb-8">
                <flux:heading size="lg" class="mb-4 text-gray-800">Order Items</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-300">
                                <th class="text-left py-3 font-semibold text-gray-800">Product</th>
                                <th class="text-left py-3 font-semibold text-gray-800">SKU</th>
                                <th class="text-center py-3 font-semibold text-gray-800">Qty</th>
                                <th class="text-right py-3 font-semibold text-gray-800">Unit Price</th>
                                <th class="text-right py-3 font-semibold text-gray-800">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr class="border-b border-gray-200">
                                    <td class="py-4">
                                        <flux:text class="font-medium">{{ $item->product?->name ?? $item->product_name ?? 'Unknown Product' }}</flux:text>
                                        @if($item->warehouse)
                                            <flux:text size="xs" class="text-gray-500 block">Warehouse: {{ $item->warehouse->name }}</flux:text>
                                        @endif
                                    </td>
                                    <td class="py-4">
                                        <flux:text class="text-gray-600">{{ $item->sku ?? $item->product?->sku ?? '-' }}</flux:text>
                                    </td>
                                    <td class="py-4 text-center">{{ $item->quantity_ordered }}</td>
                                    <td class="py-4 text-right">{{ $order->currency }} {{ number_format($item->unit_price, 2) }}</td>
                                    <td class="py-4 text-right font-semibold">{{ $order->currency }} {{ number_format($item->total_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="py-3 text-right text-gray-600">Subtotal:</td>
                                <td class="py-3 text-right font-semibold">{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</td>
                            </tr>
                            @if($order->shipping_cost > 0)
                                <tr>
                                    <td colspan="4" class="py-2 text-right text-gray-600">Shipping:</td>
                                    <td class="py-2 text-right">{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</td>
                                </tr>
                            @endif
                            @if($order->tax_amount > 0)
                                <tr>
                                    <td colspan="4" class="py-2 text-right text-gray-600">Tax (GST):</td>
                                    <td class="py-2 text-right">{{ $order->currency }} {{ number_format($order->tax_amount, 2) }}</td>
                                </tr>
                            @endif
                            @if($order->discount_amount > 0)
                                <tr>
                                    <td colspan="4" class="py-2 text-right text-green-600">Discount:</td>
                                    <td class="py-2 text-right text-green-600">-{{ $order->currency }} {{ number_format($order->discount_amount, 2) }}</td>
                                </tr>
                            @endif
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="4" class="py-4 text-right font-bold text-lg text-gray-800">Total Amount:</td>
                                <td class="py-4 text-right font-bold text-xl text-blue-600">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Payment Information -->
            @if($latestPayment)
                <div class="mb-8">
                    <flux:heading size="lg" class="mb-4 text-gray-800">Payment Information</flux:heading>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <flux:text class="text-gray-600">Payment Method:</flux:text>
                                <flux:text class="font-semibold capitalize">{{ str_replace('_', ' ', $latestPayment->payment_method) }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-600">Currency:</flux:text>
                                <flux:text class="font-semibold">{{ strtoupper($order->currency) }}</flux:text>
                            </div>
                            @if($latestPayment->paid_at)
                                <div>
                                    <flux:text class="text-gray-600">Paid At:</flux:text>
                                    <flux:text class="font-semibold">{{ $latestPayment->paid_at->format('M j, Y g:i A') }}</flux:text>
                                </div>
                            @endif
                            @if($latestPayment->transaction_id)
                                <div>
                                    <flux:text class="text-gray-600">Transaction ID:</flux:text>
                                    <flux:text class="font-mono text-sm">{{ $latestPayment->transaction_id }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Footer -->
            <div class="text-center pt-8 border-t border-gray-200">
                <flux:text class="text-gray-600">
                    Thank you for your order!
                </flux:text>
                <flux:text size="sm" class="text-gray-500 mt-2 block">
                    For any questions regarding this order, please contact our support team.
                </flux:text>
                <flux:text size="xs" class="text-gray-400 mt-4 block">
                    Generated on {{ now()->format('M j, Y g:i A') }}
                </flux:text>
            </div>
        </div>
    </div>

    <style>
        @media print {
            /* Hide ALL navigation, sidebar, and header elements */
            .no-print,
            nav,
            aside,
            header,
            /* Flux UI specific selectors */
            [data-flux-sidebar],
            [data-flux-header],
            [data-flux-navbar],
            [data-flux-sidebar-toggle],
            /* Class-based selectors for Flux */
            .border-e,
            .lg\:hidden,
            /* Any element with sidebar in x-data */
            [x-data*="sidebar"],
            [x-data*="stashable"],
            /* Common sidebar classes */
            .sidebar,
            .navigation,
            .nav-menu,
            .app-sidebar,
            .main-nav,
            /* Hide the first direct child of body that contains sidebar */
            body > div:first-child > div:first-child,
            /* Flux dropdown menus */
            [data-flux-dropdown],
            [data-flux-menu] {
                display: none !important;
                visibility: hidden !important;
                width: 0 !important;
                height: 0 !important;
                overflow: hidden !important;
                position: absolute !important;
                left: -9999px !important;
            }

            /* Reset body and html */
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                overflow: visible !important;
            }

            /* Override any grid or flex layouts that include sidebar */
            body > div,
            body > div > div {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Reset main content area */
            main,
            .main-content,
            [role="main"] {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin-left: 0 !important;
            }

            /* Make the receipt container full width */
            .min-h-screen {
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .max-w-4xl {
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 10px !important;
            }

            /* Clean up the receipt card */
            .print\:border-0,
            .bg-white.border,
            .rounded-lg.border {
                border: none !important;
                box-shadow: none !important;
            }

            .print\:shadow-none {
                box-shadow: none !important;
            }

            /* Hide any fixed/sticky elements */
            [class*="fixed"],
            [class*="sticky"] {
                position: static !important;
            }

            /* Remove any left margin/padding that might be for sidebar */
            [class*="lg:ms-"],
            [class*="lg:ml-"],
            [class*="lg:ps-"],
            [class*="lg:pl-"] {
                margin-left: 0 !important;
                padding-left: 0 !important;
            }

            /* Ensure tables print properly */
            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            /* Make sure receipt content is visible */
            .p-8 {
                padding: 15px !important;
            }

            /* Target our specific receipt classes */
            .print-receipt-page {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                z-index: 9999 !important;
                background: white !important;
            }

            .print-receipt-content {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Hide everything except the receipt */
            body > *:not(:has(.print-receipt-page)) {
                display: none !important;
            }

            /* Ensure Flux sidebar is hidden */
            [class*="border-e"][class*="border-zinc"] {
                display: none !important;
            }
        }

        /* Print-specific page setup */
        @page {
            margin: 10mm;
            size: A4;
        }
    </style>
</div>
