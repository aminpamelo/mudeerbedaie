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
            'agent',
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

    public function downloadDeliveryNote()
    {
        $order = $this->order;

        $pdf = Pdf::loadView('livewire.admin.orders.order-delivery-note-pdf', compact('order'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
            ]);

        $filename = 'delivery-note-'.$order->order_number.'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function getDeliveryNoteNumber(): string
    {
        $date = $this->order->order_date ?? $this->order->created_at;
        $yearMonth = $date->format('d/m/Y');
        $sequence = str_pad($this->order->id, 5, '0', STR_PAD_LEFT);
        return "DO-{$sequence}";
    }

    public function getInvoiceNumber(): string
    {
        $date = $this->order->order_date ?? $this->order->created_at;
        $yearMonth = $date->format('y/m');
        $sequence = str_pad($this->order->id, 3, '0', STR_PAD_LEFT);
        return "INV{$yearMonth}-{$sequence}";
    }

    public function numberToWords(float $number): string
    {
        $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

        $integer = floor($number);
        $decimal = round(($number - $integer) * 100);

        $words = '';

        if ($integer >= 1000) {
            $thousands = floor($integer / 1000);
            $words .= $this->convertHundreds($thousands, $ones, $tens) . ' THOUSAND ';
            $integer %= 1000;
        }

        if ($integer >= 100) {
            $words .= $this->convertHundreds($integer, $ones, $tens);
        } elseif ($integer > 0) {
            $words .= $this->convertTens($integer, $ones, $tens);
        }

        $words = trim($words);

        if ($decimal > 0) {
            $words .= ' AND CENTS ' . $this->convertTens($decimal, $ones, $tens);
        }

        return 'RINGGIT MALAYSIA : ' . $words . ' ONLY';
    }

    private function convertHundreds(int $number, array $ones, array $tens): string
    {
        $result = '';
        if ($number >= 100) {
            $result .= $ones[floor($number / 100)] . ' HUNDRED ';
            $number %= 100;
        }
        $result .= $this->convertTens($number, $ones, $tens);
        return trim($result);
    }

    private function convertTens(int $number, array $ones, array $tens): string
    {
        if ($number < 20) {
            return $ones[$number];
        }
        return $tens[floor($number / 10)] . ' ' . $ones[$number % 10];
    }
}; ?>

<div class="min-h-screen bg-white print-receipt-page">
    <div class="max-w-4xl mx-auto p-4 print-receipt-content">
        <!-- Header with Action Buttons -->
        <div class="mb-4 flex items-center justify-between no-print">
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
                <flux:button wire:click="downloadDeliveryNote" variant="outline">
                    <div class="flex items-center justify-center">
                        <flux:icon name="truck" class="w-4 h-4 mr-1" />
                        Delivery Note
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
        <div class="bg-white border border-gray-200 rounded-lg print:border-0 print:shadow-none overflow-hidden">
            <!-- Company Header -->
            <div class="border-b-4 border-purple-800 px-6 py-4">
                <div class="text-center">
                    <h1 class="text-xl font-bold tracking-wide text-gray-900">{{ config('app.company.name') }}</h1>
                    <p class="text-gray-500 text-xs">({{ config('app.company.registration') }})</p>
                    <p class="text-gray-600 text-xs mt-1">
                        {{ config('app.company.address_line_1') }}, {{ config('app.company.address_line_2') }}
                    </p>
                    <p class="text-gray-600 text-xs">
                        Phone: {{ config('app.company.phone') }} &nbsp;&nbsp; email: {{ config('app.company.email') }}
                    </p>
                </div>
            </div>

            <div class="p-5">
                <!-- Invoice Title and Document Info -->
                <div class="flex justify-between items-start mb-4">
                    <!-- Billing Address -->
                    @php
                        $billingAddress = $order->billingAddress();
                    @endphp
                    <div class="max-w-xs">
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide mb-1">Billing Address</p>
                        <p class="font-bold text-gray-900 text-sm">
                            {{ $billingAddress?->first_name ?? $order->getCustomerName() }}
                            {{ $billingAddress?->last_name ?? '' }}
                        </p>
                        @if($billingAddress?->company)
                            <p class="text-gray-700 text-xs">{{ $billingAddress->company }}</p>
                        @endif
                        @if($billingAddress)
                            <p class="text-gray-700 text-xs">{{ $billingAddress->address_line_1 }}</p>
                            @if($billingAddress->address_line_2)
                                <p class="text-gray-700 text-xs">{{ $billingAddress->address_line_2 }}</p>
                            @endif
                            <p class="text-gray-700 text-xs">{{ $billingAddress->city }}, {{ $billingAddress->postal_code }} {{ $billingAddress->state }}</p>
                        @endif
                        @php
                            $phone = $order->customer_phone ?? $billingAddress?->phone ?? null;
                        @endphp
                        @if($phone)
                            <p class="text-gray-700 text-xs mt-1">Tel: {{ $phone }}</p>
                        @endif
                    </div>

                    <!-- Invoice Label & Details -->
                    <div class="text-right">
                        <h2 class="text-3xl font-bold text-purple-800 mb-2">INVOICE</h2>
                        <table class="text-xs ml-auto">
                            <tr>
                                <td class="text-gray-600 pr-3 py-0.5">Doc No. :</td>
                                <td class="font-semibold text-gray-900 py-0.5">{{ $this->getInvoiceNumber() }}</td>
                            </tr>
                            <tr>
                                <td class="text-gray-600 pr-3 py-0.5">Date :</td>
                                <td class="font-semibold text-gray-900 py-0.5">{{ ($order->order_date ?? $order->created_at)->format('d/m/Y') }}</td>
                            </tr>
                            <tr>
                                <td class="text-gray-600 pr-3 py-0.5">Payment Terms :</td>
                                <td class="font-semibold text-gray-900 py-0.5">Immediate</td>
                            </tr>
                            @if($order->agent)
                                <tr>
                                    <td class="text-gray-600 pr-3 py-0.5">Sales Executive :</td>
                                    <td class="font-semibold text-gray-900 py-0.5 uppercase">{{ $order->agent->name }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="text-gray-600 pr-3 py-0.5">Order Ref :</td>
                                <td class="font-semibold text-gray-900 py-0.5">{{ $order->order_number }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-4">
                    <table class="w-full border-collapse text-xs">
                        <thead>
                            <tr class="bg-purple-800 text-white">
                                <th class="py-2 px-2 text-left font-semibold w-8">No</th>
                                <th class="py-2 px-2 text-left font-semibold w-20">Item Code</th>
                                <th class="py-2 px-2 text-left font-semibold">Description</th>
                                <th class="py-2 px-2 text-center font-semibold w-16">Qty</th>
                                <th class="py-2 px-2 text-right font-semibold w-20">Price/Unit</th>
                                <th class="py-2 px-2 text-center font-semibold w-12">Disc</th>
                                <th class="py-2 px-2 text-right font-semibold w-24">Sub Total ({{ $order->currency }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $index => $item)
                                <tr class="border-b border-gray-200">
                                    <td class="py-2 px-2">{{ $index + 1 }}</td>
                                    <td class="py-2 px-2 font-medium">{{ strtoupper(substr($item->sku ?? $item->product?->sku ?? 'ITEM', 0, 10)) }}</td>
                                    <td class="py-2 px-2">
                                        {{ strtoupper($item->product?->name ?? $item->product_name ?? 'Product') }}
                                        @if($item->warehouse)
                                            <span class="text-[10px] text-gray-500 block">From: {{ $item->warehouse->name }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 text-center">{{ number_format($item->quantity_ordered, 2) }}</td>
                                    <td class="py-2 px-2 text-right">{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="py-2 px-2 text-center">
                                        @if($item->discount_amount > 0)
                                            {{ number_format($item->discount_amount, 2) }}
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 text-right font-semibold">{{ number_format($item->total_price, 2) }}</td>
                                </tr>
                            @endforeach
                            @if($order->shipping_cost > 0)
                                <tr class="border-b border-gray-200">
                                    <td class="py-2 px-2">{{ $order->items->count() + 1 }}</td>
                                    <td class="py-2 px-2 font-medium">SHIPPING</td>
                                    <td class="py-2 px-2">SHIPPING / DELIVERY CHARGE</td>
                                    <td class="py-2 px-2 text-center">1.00</td>
                                    <td class="py-2 px-2 text-right">{{ number_format($order->shipping_cost, 2) }}</td>
                                    <td class="py-2 px-2 text-center"></td>
                                    <td class="py-2 px-2 text-right font-semibold">{{ number_format($order->shipping_cost, 2) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Amount in Words and Total -->
                <div class="border-t-2 border-gray-300 pt-3">
                    <div class="flex justify-between items-center">
                        <p class="text-[10px] text-gray-600 uppercase tracking-wide flex-1 pr-4">
                            {{ $this->numberToWords($order->total_amount) }}
                        </p>
                        <div class="text-right flex items-center gap-4">
                            <span class="text-gray-600 font-semibold text-sm">Total :</span>
                            <span class="text-xl font-bold text-gray-900">{{ number_format($order->total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="font-semibold text-gray-800 text-xs mb-1">Note :</p>
                        <ol class="text-[10px] text-gray-600 space-y-0.5 list-decimal list-inside">
                            <li>All cheques should be crossed and made payable to <span class="font-semibold">{{ config('app.company.name') }}</span></li>
                            <li>Good sold are neither returnable nor refundable.</li>
                        </ol>
                        <div class="mt-2">
                            <p class="text-[10px] text-gray-600">Bank account No:</p>
                            <p class="text-[10px] font-semibold text-gray-800">{{ config('app.company.bank_name') }} {{ config('app.company.bank_account') }}</p>
                        </div>
                    </div>
                    <div class="text-right flex items-end justify-end">
                        <p class="text-[10px] italic text-gray-500">Computer generated, no signature required</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 pt-3 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="bg-purple-800 text-white px-3 py-1.5 rounded-r-full">
                            <p class="text-[10px] font-semibold">{{ config('app.company.name') }} ({{ config('app.company.registration') }} ({{ config('app.company.tax_id') }}))</p>
                        </div>
                        <p class="text-xs text-gray-500">1 of 1</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print,
            nav, aside, header,
            [data-flux-sidebar], [data-flux-header], [data-flux-navbar], [data-flux-sidebar-toggle],
            .border-e, .lg\:hidden, [x-data*="sidebar"], [x-data*="stashable"],
            .sidebar, .navigation, .nav-menu, .app-sidebar, .main-nav,
            body > div:first-child > div:first-child,
            [data-flux-dropdown], [data-flux-menu] {
                display: none !important;
                visibility: hidden !important;
                width: 0 !important;
                height: 0 !important;
                position: absolute !important;
                left: -9999px !important;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body > div, body > div > div {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            main, .main-content, [role="main"] {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .min-h-screen {
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .max-w-4xl {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 5mm !important;
            }

            .print\:border-0 {
                border: none !important;
            }

            .print-receipt-page {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                z-index: 9999 !important;
                background: white !important;
            }

            .bg-purple-800 {
                background-color: #6b21a8 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            [class*="border-e"][class*="border-zinc"] {
                display: none !important;
            }
        }

        @page {
            margin: 5mm;
            size: A4;
        }
    </style>
</div>
