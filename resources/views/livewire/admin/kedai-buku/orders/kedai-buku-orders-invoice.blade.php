<?php

use App\Models\ProductOrder;
use Livewire\Volt\Component;

new class extends Component
{
    public ProductOrder $order;

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load(['items.product', 'agent', 'payments', 'addresses']);

        // Verify this is a kedai buku order
        if (!$this->order->agent || !$this->order->agent->isBookstore()) {
            abort(404, 'Order not found or not a bookstore order.');
        }
    }

    public function print(): void
    {
        $this->dispatch('print-invoice');
    }
}; ?>

<div>
    <!-- Print Controls (hidden when printing) -->
    <div class="mb-6 flex items-center justify-between print:hidden">
        <div>
            <flux:heading size="xl">Invois {{ $order->order_number }}</flux:heading>
            <flux:text class="mt-2">Invoice for {{ $order->agent->name }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button variant="outline" :href="route('agents-kedai-buku.orders.show', $order)" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                    Kembali
                </div>
            </flux:button>
            <flux:button variant="primary" onclick="window.print()">
                <div class="flex items-center justify-center">
                    <flux:icon name="printer" class="w-4 h-4 mr-2" />
                    Cetak
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Invoice Document -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-8 print:shadow-none print:border-none print:p-0">
        <!-- Header -->
        <div class="flex justify-between items-start border-b border-gray-200 dark:border-zinc-700 pb-6 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-zinc-100">INVOIS</h1>
                <p class="text-gray-600 dark:text-zinc-400 mt-1">Invoice</p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-gray-900 dark:text-zinc-100">{{ config('app.name') }}</h2>
                <p class="text-gray-600 dark:text-zinc-400 text-sm mt-2">
                    123 Jalan Utama<br>
                    50000 Kuala Lumpur<br>
                    Malaysia<br>
                    Tel: +60 3-1234 5678
                </p>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div>
                <h3 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Bil Kepada / Bill To</h3>
                <div class="text-gray-900 dark:text-zinc-100">
                    <p class="font-semibold">{{ $order->agent->name }}</p>
                    @if($order->agent->company_name)
                        <p>{{ $order->agent->company_name }}</p>
                    @endif
                    @if($order->agent->contact_person)
                        <p>Pengurus: {{ $order->agent->contact_person }}</p>
                    @endif
                    @if($order->agent->address)
                        <p class="mt-2">
                            {{ $order->agent->address['street'] ?? '' }}<br>
                            {{ $order->agent->address['postal_code'] ?? '' }} {{ $order->agent->address['city'] ?? '' }}<br>
                            {{ $order->agent->address['state'] ?? '' }}, {{ $order->agent->address['country'] ?? 'Malaysia' }}
                        </p>
                    @endif
                    @if($order->agent->phone)
                        <p class="mt-2">Tel: {{ $order->agent->phone }}</p>
                    @endif
                    @if($order->agent->email)
                        <p>Email: {{ $order->agent->email }}</p>
                    @endif
                </div>
            </div>

            <div class="text-right">
                <div class="inline-block text-left">
                    <table class="text-sm">
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">No. Invois:</td>
                            <td class="font-semibold text-gray-900 dark:text-zinc-100">{{ $order->order_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Tarikh:</td>
                            <td class="text-gray-900 dark:text-zinc-100">{{ $order->order_date?->format('d/m/Y') ?? $order->created_at->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Kod Kedai Buku:</td>
                            <td class="text-gray-900 dark:text-zinc-100">{{ $order->agent->agent_code }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Tier Harga:</td>
                            <td class="text-gray-900 dark:text-zinc-100">{{ ucfirst($order->agent->pricing_tier ?? 'standard') }}</td>
                        </tr>
                        @if($order->required_delivery_date)
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Tarikh Hantar:</td>
                            <td class="text-gray-900 dark:text-zinc-100">{{ $order->required_delivery_date->format('d/m/Y') }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Status:</td>
                            <td>
                                @php
                                    $statusColor = match($order->status) {
                                        'delivered' => 'text-green-600',
                                        'cancelled' => 'text-red-600',
                                        'processing', 'shipped' => 'text-blue-600',
                                        default => 'text-yellow-600',
                                    };
                                @endphp
                                <span class="font-medium {{ $statusColor }}">{{ ucfirst($order->status) }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="mb-8">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-zinc-900">
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">#</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Produk / Product</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">SKU</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-zinc-100">Kuantiti</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-zinc-100">Harga Unit</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-zinc-100">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $index => $item)
                        <tr>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-gray-900 dark:text-zinc-100">{{ $index + 1 }}</td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-gray-900 dark:text-zinc-100">
                                {{ $item->product_name }}
                                @if($item->variant_name)
                                    <span class="text-gray-500 dark:text-zinc-400">({{ $item->variant_name }})</span>
                                @endif
                            </td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-gray-600 dark:text-zinc-400">{{ $item->sku ?? '-' }}</td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-center text-gray-900 dark:text-zinc-100">{{ $item->quantity_ordered }}</td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-right text-gray-900 dark:text-zinc-100">RM {{ number_format($item->unit_price, 2) }}</td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format($item->total_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="flex justify-end mb-8">
            <div class="w-80">
                <table class="w-full text-sm">
                    <tr>
                        <td class="py-2 text-gray-600 dark:text-zinc-400">Jumlah Kecil / Subtotal:</td>
                        <td class="py-2 text-right text-gray-900 dark:text-zinc-100">RM {{ number_format($order->subtotal, 2) }}</td>
                    </tr>
                    @if($order->discount_amount > 0)
                        <tr>
                            <td class="py-2 text-green-600 dark:text-green-400">Diskaun / Discount:</td>
                            <td class="py-2 text-right text-green-600 dark:text-green-400">- RM {{ number_format($order->discount_amount, 2) }}</td>
                        </tr>
                    @endif
                    @if($order->shipping_cost > 0)
                        <tr>
                            <td class="py-2 text-gray-600 dark:text-zinc-400">Penghantaran / Shipping:</td>
                            <td class="py-2 text-right text-gray-900 dark:text-zinc-100">RM {{ number_format($order->shipping_cost, 2) }}</td>
                        </tr>
                    @endif
                    @if($order->tax_amount > 0)
                        <tr>
                            <td class="py-2 text-gray-600 dark:text-zinc-400">Cukai / Tax:</td>
                            <td class="py-2 text-right text-gray-900 dark:text-zinc-100">RM {{ number_format($order->tax_amount, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="border-t border-gray-200 dark:border-zinc-700">
                        <td class="py-3 font-bold text-gray-900 dark:text-zinc-100 text-lg">Jumlah Keseluruhan / Total:</td>
                        <td class="py-3 text-right font-bold text-gray-900 dark:text-zinc-100 text-lg">RM {{ number_format($order->total_amount, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="border-t border-gray-200 dark:border-zinc-700 pt-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-3">Maklumat Pembayaran / Payment Information</h3>
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-600 dark:text-zinc-400">Kaedah Pembayaran: <span class="text-gray-900 dark:text-zinc-100">{{ ucfirst($order->payment_method ?? 'Credit') }}</span></p>
                    <p class="text-gray-600 dark:text-zinc-400">Status Pembayaran:
                        @php
                            $paymentStatusColor = match($order->payment_status ?? 'pending') {
                                'paid' => 'text-green-600',
                                'partial' => 'text-yellow-600',
                                default => 'text-red-600',
                            };
                        @endphp
                        <span class="font-medium {{ $paymentStatusColor }}">{{ ucfirst($order->payment_status ?? 'Pending') }}</span>
                    </p>
                </div>
                <div>
                    <p class="text-gray-600 dark:text-zinc-400">Had Kredit: <span class="text-gray-900 dark:text-zinc-100">RM {{ number_format($order->agent->credit_limit, 2) }}</span></p>
                    <p class="text-gray-600 dark:text-zinc-400">Baki Tertunggak: <span class="text-gray-900 dark:text-zinc-100">RM {{ number_format($order->agent->outstanding_balance, 2) }}</span></p>
                </div>
            </div>
        </div>

        <!-- Terms & Notes -->
        @if($order->customer_notes)
            <div class="border-t border-gray-200 dark:border-zinc-700 pt-6 mb-6">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Nota / Notes</h3>
                <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $order->customer_notes }}</p>
            </div>
        @endif

        <div class="border-t border-gray-200 dark:border-zinc-700 pt-6">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Terma & Syarat / Terms & Conditions</h3>
            <ul class="text-xs text-gray-500 dark:text-zinc-400 space-y-1">
                <li>1. Sila selesaikan pembayaran dalam tempoh 30 hari dari tarikh invois.</li>
                <li>2. Semua barangan yang dijual adalah tidak boleh dipulangkan.</li>
                <li>3. Harga tertakluk kepada perubahan tanpa notis awal.</li>
                <li>4. Diskaun tier akan kekal selagi akaun dalam status aktif.</li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-zinc-700 text-center">
            <p class="text-sm text-gray-500 dark:text-zinc-400">Terima kasih atas pesanan anda! / Thank you for your order!</p>
            <p class="text-xs text-gray-400 dark:text-zinc-500 mt-2">Invois ini dijana secara automatik dan sah tanpa tandatangan.</p>
        </div>
    </div>

    <!-- Print Styles -->
    <style>
        @media print {
            body {
                background: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .print\:hidden {
                display: none !important;
            }
            .print\:shadow-none {
                box-shadow: none !important;
            }
            .print\:border-none {
                border: none !important;
            }
            .print\:p-0 {
                padding: 0 !important;
            }
            /* Force colors in print */
            .text-green-600, .dark\:text-green-400 {
                color: #16a34a !important;
            }
            .text-red-600 {
                color: #dc2626 !important;
            }
            .text-yellow-600 {
                color: #ca8a04 !important;
            }
            .text-blue-600 {
                color: #2563eb !important;
            }
        }
    </style>
</div>
