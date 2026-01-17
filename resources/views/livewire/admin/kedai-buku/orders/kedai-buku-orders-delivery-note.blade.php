<?php

use App\Models\ProductOrder;
use Livewire\Volt\Component;

new class extends Component
{
    public ProductOrder $order;

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load(['items.product', 'agent', 'addresses']);

        // Verify this is a kedai buku order
        if (!$this->order->agent || !$this->order->agent->isBookstore()) {
            abort(404, 'Order not found or not a bookstore order.');
        }
    }

    public function print(): void
    {
        $this->dispatch('print-delivery-note');
    }
}; ?>

<div>
    <!-- Print Controls (hidden when printing) -->
    <div class="mb-6 flex items-center justify-between print:hidden">
        <div>
            <flux:heading size="xl">Nota Penghantaran {{ $order->order_number }}</flux:heading>
            <flux:text class="mt-2">Delivery Note for {{ $order->agent->name }}</flux:text>
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

    <!-- Delivery Note Document -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-8 print:shadow-none print:border-none print:p-0">
        <!-- Header -->
        <div class="flex justify-between items-start border-b border-gray-200 dark:border-zinc-700 pb-6 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-zinc-100">NOTA PENGHANTARAN</h1>
                <p class="text-gray-600 dark:text-zinc-400 mt-1">Delivery Note</p>
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

        <!-- Delivery Details -->
        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div>
                <h3 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Dihantar Kepada / Deliver To</h3>
                <div class="text-gray-900 dark:text-zinc-100">
                    <p class="font-semibold text-lg">{{ $order->agent->name }}</p>
                    @if($order->agent->company_name)
                        <p class="text-gray-600 dark:text-zinc-400">{{ $order->agent->company_name }}</p>
                    @endif
                    @if($order->agent->contact_person)
                        <p class="mt-2"><strong>Pengurus:</strong> {{ $order->agent->contact_person }}</p>
                    @endif
                    @if($order->agent->address)
                        <p class="mt-2">
                            {{ $order->agent->address['street'] ?? '' }}<br>
                            {{ $order->agent->address['postal_code'] ?? '' }} {{ $order->agent->address['city'] ?? '' }}<br>
                            {{ $order->agent->address['state'] ?? '' }}, {{ $order->agent->address['country'] ?? 'Malaysia' }}
                        </p>
                    @endif
                    @if($order->agent->phone)
                        <p class="mt-2"><strong>Tel:</strong> {{ $order->agent->phone }}</p>
                    @endif
                </div>
            </div>

            <div class="text-right">
                <div class="inline-block text-left bg-gray-50 dark:bg-zinc-900 rounded-lg p-4">
                    <table class="text-sm">
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1 font-medium">No. Nota:</td>
                            <td class="font-bold text-gray-900 dark:text-zinc-100">DN-{{ $order->order_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">No. Pesanan:</td>
                            <td class="text-gray-900 dark:text-zinc-100">{{ $order->order_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Tarikh:</td>
                            <td class="text-gray-900 dark:text-zinc-100">{{ now()->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Tarikh Pesanan:</td>
                            <td class="text-gray-900 dark:text-zinc-100">{{ $order->order_date?->format('d/m/Y') ?? $order->created_at->format('d/m/Y') }}</td>
                        </tr>
                        @if($order->required_delivery_date)
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Tarikh Hantar Diperlukan:</td>
                            <td class="font-medium text-blue-600 dark:text-blue-400">{{ $order->required_delivery_date->format('d/m/Y') }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-gray-500 dark:text-zinc-400 pr-4 py-1">Kod Kedai Buku:</td>
                            <td class="text-gray-900 dark:text-zinc-100">{{ $order->agent->agent_code }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="mb-8">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-3">Senarai Barangan / Item List</h3>
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-zinc-900">
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">#</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Produk / Product</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">SKU</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-zinc-100">Kuantiti Dipesan</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-zinc-100">Kuantiti Dihantar</th>
                        <th class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-zinc-100">Diperiksa</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalItems = 0; @endphp
                    @foreach($order->items as $index => $item)
                        @php $totalItems += $item->quantity_ordered; @endphp
                        <tr>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-gray-900 dark:text-zinc-100">{{ $index + 1 }}</td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-gray-900 dark:text-zinc-100">
                                <div class="font-medium">{{ $item->product_name }}</div>
                                @if($item->variant_name)
                                    <div class="text-gray-500 dark:text-zinc-400 text-xs">Variant: {{ $item->variant_name }}</div>
                                @endif
                            </td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-gray-600 dark:text-zinc-400">{{ $item->sku ?? '-' }}</td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-center font-medium text-gray-900 dark:text-zinc-100">{{ $item->quantity_ordered }}</td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-center">
                                <div class="w-16 h-8 border border-gray-300 dark:border-zinc-600 rounded mx-auto"></div>
                            </td>
                            <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-center">
                                <div class="w-8 h-8 border border-gray-300 dark:border-zinc-600 rounded mx-auto"></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-zinc-900">
                        <td colspan="3" class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm font-semibold text-gray-900 dark:text-zinc-100 text-right">Jumlah / Total:</td>
                        <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3 text-sm text-center font-bold text-gray-900 dark:text-zinc-100">{{ $totalItems }}</td>
                        <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3"></td>
                        <td class="border border-gray-200 dark:border-zinc-700 px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Notes -->
        @if($order->customer_notes)
            <div class="mb-8 p-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                <h3 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 uppercase tracking-wider mb-2">Nota Pesanan / Order Notes</h3>
                <p class="text-sm text-yellow-700 dark:text-yellow-300">{{ $order->customer_notes }}</p>
            </div>
        @endif

        <!-- Delivery Confirmation Section -->
        <div class="border-t border-gray-200 dark:border-zinc-700 pt-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-4">Pengesahan Penerimaan / Delivery Confirmation</h3>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Sender Signature -->
                <div class="border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-zinc-400 mb-2">Dihantar oleh / Delivered by:</p>
                    <div class="h-20 border-b border-gray-300 dark:border-zinc-600 mb-2"></div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-zinc-400">Nama:</p>
                            <div class="border-b border-gray-300 dark:border-zinc-600 h-6"></div>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-zinc-400">Tarikh:</p>
                            <div class="border-b border-gray-300 dark:border-zinc-600 h-6"></div>
                        </div>
                    </div>
                </div>

                <!-- Receiver Signature -->
                <div class="border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-zinc-400 mb-2">Diterima oleh / Received by:</p>
                    <div class="h-20 border-b border-gray-300 dark:border-zinc-600 mb-2"></div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-zinc-400">Nama:</p>
                            <div class="border-b border-gray-300 dark:border-zinc-600 h-6"></div>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-zinc-400">Tarikh:</p>
                            <div class="border-b border-gray-300 dark:border-zinc-600 h-6"></div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <p class="text-gray-500 dark:text-zinc-400 text-sm">Cop Syarikat / Company Stamp:</p>
                        <div class="h-16 border border-dashed border-gray-300 dark:border-zinc-600 mt-1 rounded"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Condition Notes -->
        <div class="border border-gray-200 dark:border-zinc-700 rounded-lg p-4 mb-6">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Keadaan Barangan Semasa Penerimaan / Item Condition Upon Receipt</h3>
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-zinc-300">
                    <input type="checkbox" class="rounded border-gray-300 dark:border-zinc-600" disabled>
                    Semua barangan dalam keadaan baik / All items in good condition
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-zinc-300">
                    <input type="checkbox" class="rounded border-gray-300 dark:border-zinc-600" disabled>
                    Terdapat kerosakan / Some damage noted
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-zinc-300">
                    <input type="checkbox" class="rounded border-gray-300 dark:border-zinc-600" disabled>
                    Kurang barang / Short shipment
                </label>
            </div>
            <div class="mt-3">
                <p class="text-sm text-gray-500 dark:text-zinc-400">Catatan / Remarks:</p>
                <div class="h-16 border border-gray-300 dark:border-zinc-600 rounded mt-1"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-zinc-700">
            <div class="text-center text-sm text-gray-500 dark:text-zinc-400">
                <p>Sila tandatangan dan kembalikan salinan kepada pemandu / Please sign and return a copy to the driver</p>
                <p class="mt-2 text-xs">Dokumen ini dijana secara automatik pada {{ now()->format('d/m/Y H:i') }}</p>
            </div>
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
            /* Ensure borders print */
            table, th, td {
                border-color: #e5e7eb !important;
            }
            /* Force colors in print */
            .text-blue-600, .dark\:text-blue-400 {
                color: #2563eb !important;
            }
            .text-yellow-700, .dark\:text-yellow-300 {
                color: #a16207 !important;
            }
            .bg-yellow-50, .dark\:bg-yellow-900\/30 {
                background-color: #fefce8 !important;
            }
        }
    </style>
</div>
