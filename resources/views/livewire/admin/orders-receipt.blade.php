<?php
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Volt\Component;

new class extends Component {
    public Order $order;

    public function mount()
    {
        $this->order->load(['student.user', 'course', 'enrollment', 'items']);

        // Only allow viewing receipts for paid orders
        if (!$this->order->isPaid()) {
            abort(404, 'Receipt not available for unpaid orders');
        }
    }

    public function downloadPdf()
    {
        // Load the order with relationships for PDF generation
        $order = $this->order->load(['student.user', 'course', 'enrollment', 'items']);

        // Generate PDF from a PDF-optimized view
        $pdf = Pdf::loadView('livewire.admin.orders-receipt-pdf', compact('order'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
            ]);

        $filename = 'receipt-' . $order->order_number . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function with(): array
    {
        return [];
    }
}; ?>

<div class="min-h-screen bg-white">
    <div class="max-w-4xl mx-auto p-8">
        <!-- Header with Print Button -->
        <div class="mb-8 flex items-center justify-between no-print">
            <div>
                <flux:heading size="xl">Receipt</flux:heading>
                <flux:text class="mt-1 text-gray-600">Order {{ $order->order_number }}</flux:text>
            </div>
            <div class="flex gap-3">
                <flux:button href="{{ route('orders.show', $order) }}" variant="outline">
                    ← Back to Order
                </flux:button>
                <flux:button wire:click="downloadPdf" variant="outline">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                        Download PDF
                    </div>
                </flux:button>
                <flux:button onclick="window.print()" variant="primary">
                    Print Receipt
                </flux:button>
            </div>
        </div>

        <!-- Receipt Content -->
        <div class="bg-white border border-gray-200 rounded-lg p-8 print:border-0 print:shadow-none">
            <!-- Company Header -->
            <div class="text-center mb-8">
                <flux:heading size="2xl" class="text-gray-800">{{ config('app.name') }}</flux:heading>
                <flux:text class="text-gray-600 mt-2">Payment Receipt</flux:text>
            </div>

            <!-- Receipt Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <!-- Left Column - Receipt Info -->
                <div>
                    <flux:heading size="lg" class="mb-4 text-gray-800">Receipt Details</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Receipt Number:</flux:text>
                            <flux:text class="font-semibold">{{ $order->order_number }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Date Issued:</flux:text>
                            <flux:text class="font-semibold">{{ $order->paid_at?->format('M j, Y g:i A') }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Payment Status:</flux:text>
                            <flux:badge variant="success">{{ $order->status_label }}</flux:badge>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Billing Period:</flux:text>
                            <flux:text class="font-semibold">{{ $order->getPeriodDescription() }}</flux:text>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Student Info -->
                <div>
                    <flux:heading size="lg" class="mb-4 text-gray-800">Student Information</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Name:</flux:text>
                            <flux:text class="font-semibold">{{ $order->student->user->name }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Email:</flux:text>
                            <flux:text class="font-semibold">{{ $order->student->user->email }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Student ID:</flux:text>
                            <flux:text class="font-semibold">{{ $order->student->student_id }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Information -->
            <div class="mb-8">
                <flux:heading size="lg" class="mb-4 text-gray-800">Course Information</flux:heading>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <flux:text class="text-gray-600">Course Name:</flux:text>
                            <flux:text class="font-semibold text-lg">{{ $order->course->name }}</flux:text>
                        </div>
                        @if($order->course->description)
                            <div>
                                <flux:text class="text-gray-600">Description:</flux:text>
                                <flux:text class="font-semibold">{{ Str::limit($order->course->description, 100) }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="mb-8">
                <flux:heading size="lg" class="mb-4 text-gray-800">Payment Details</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-300">
                                <th class="text-left py-3 font-semibold text-gray-800">Description</th>
                                <th class="text-center py-3 font-semibold text-gray-800">Qty</th>
                                <th class="text-right py-3 font-semibold text-gray-800">Unit Price</th>
                                <th class="text-right py-3 font-semibold text-gray-800">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr class="border-b border-gray-200">
                                    <td class="py-4">
                                        <flux:text class="font-medium">{{ $item->description }}</flux:text>
                                        @if($item->stripe_line_item_id)
                                            <flux:text size="xs" class="text-gray-500 block">ID: {{ $item->stripe_line_item_id }}</flux:text>
                                        @endif
                                    </td>
                                    <td class="py-4 text-center">{{ $item->quantity }}</td>
                                    <td class="py-4 text-right">{{ $item->formatted_unit_price }}</td>
                                    <td class="py-4 text-right font-semibold">{{ $item->formatted_total_price }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500">
                                        No items found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="3" class="py-4 text-right font-bold text-lg text-gray-800">Total Amount:</td>
                                <td class="py-4 text-right font-bold text-xl text-gray-800">{{ $order->formatted_amount }}</td>
                            </tr>
                            @if($order->stripe_fee)
                                <tr>
                                    <td colspan="3" class="py-2 text-right text-gray-600">Processing Fee:</td>
                                    <td class="py-2 text-right text-gray-600">-RM {{ number_format($order->stripe_fee, 2) }}</td>
                                </tr>
                            @endif
                            @if($order->net_amount && $order->net_amount != $order->amount)
                                <tr>
                                    <td colspan="3" class="py-2 text-right font-semibold text-gray-700">Net Amount:</td>
                                    <td class="py-2 text-right font-semibold text-gray-700">RM {{ number_format($order->net_amount, 2) }}</td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="mb-8">
                <flux:heading size="lg" class="mb-4 text-gray-800">Payment Information</flux:heading>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($order->stripe_charge_id)
                            <div>
                                <flux:text class="text-gray-600">Transaction ID:</flux:text>
                                <flux:text class="font-mono text-sm">{{ $order->stripe_charge_id }}</flux:text>
                            </div>
                        @endif
                        @if($order->stripe_payment_intent_id)
                            <div>
                                <flux:text class="text-gray-600">Payment Intent ID:</flux:text>
                                <flux:text class="font-mono text-sm">{{ $order->stripe_payment_intent_id }}</flux:text>
                            </div>
                        @endif
                        <div>
                            <flux:text class="text-gray-600">Payment Method:</flux:text>
                            <flux:text class="font-semibold">Credit/Debit Card</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-gray-600">Currency:</flux:text>
                            <flux:text class="font-semibold">{{ strtoupper($order->currency) }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center pt-8 border-t border-gray-200">
                <flux:text class="text-gray-600">
                    Thank you for your payment. This receipt serves as proof of your transaction.
                </flux:text>
                <flux:text size="sm" class="text-gray-500 mt-2 block">
                    For any questions regarding this receipt, please contact our support team.
                </flux:text>
                <flux:text size="xs" class="text-gray-400 mt-4 block">
                    Generated on {{ now()->format('M j, Y g:i A') }}
                </flux:text>
            </div>
        </div>
    </div>
    
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 20px;
                background: white;
            }
            
            .min-h-screen {
                min-height: auto;
            }
            
            .print\\:border-0 {
                border: 0 !important;
            }
            
            .print\\:shadow-none {
                box-shadow: none !important;
            }
        }
    </style>
</div>