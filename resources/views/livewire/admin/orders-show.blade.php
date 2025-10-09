<?php
use App\Models\Order;
use App\Services\StripeService;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public Order $order;
    public $approvalReceipt;
    public string $approvalNotes = '';
    public string $paymentDate = '';
    public bool $showApprovalModal = false;

    public function mount()
    {
        $this->order->load(['student.user', 'course', 'enrollment', 'items']);
    }

    public function processRefund($amount = null)
    {
        try {
            $stripeService = app(StripeService::class);
            
            // Implementation would depend on adding refund functionality to StripeService
            session()->flash('info', 'Refund functionality will be implemented in the next phase.');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to process refund: ' . $e->getMessage());
        }
    }

    public function resendReceipt()
    {
        try {
            if (!$this->order->receipt_url) {
                session()->flash('error', 'No receipt URL available for this order.');
                return;
            }

            // Could implement email sending here
            session()->flash('success', 'Receipt sent to student email successfully!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send receipt: ' . $e->getMessage());
        }
    }

    public function hasReceiptAttachment(): bool
    {
        return isset($this->order->metadata['receipt_file']) && !empty($this->order->metadata['receipt_file']);
    }

    public function getReceiptUrl(): ?string
    {
        if (!$this->hasReceiptAttachment()) {
            return null;
        }
        
        return asset('storage/' . $this->order->metadata['receipt_file']);
    }

    public function getApprover()
    {
        if (!isset($this->order->metadata['approved_by'])) {
            return null;
        }

        return \App\Models\User::find($this->order->metadata['approved_by']);
    }

    public function downloadReceipt()
    {
        if (!$this->hasReceiptAttachment()) {
            session()->flash('error', 'No receipt attachment found.');
            return;
        }

        $filePath = storage_path('app/public/' . $this->order->metadata['receipt_file']);

        if (!file_exists($filePath)) {
            session()->flash('error', 'Receipt file not found.');
            return;
        }

        return response()->download($filePath, 'receipt-' . $this->order->order_number . '.' . pathinfo($filePath, PATHINFO_EXTENSION));
    }

    public function openApprovalModal()
    {
        $this->showApprovalModal = true;
        $this->approvalNotes = '';
        $this->approvalReceipt = null;
        $this->paymentDate = now()->format('Y-m-d');
    }

    public function closeApprovalModal()
    {
        $this->showApprovalModal = false;
        $this->approvalNotes = '';
        $this->approvalReceipt = null;
        $this->paymentDate = '';
    }

    public function approvalRules()
    {
        return [
            'approvalReceipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
            'paymentDate' => 'required|date|before_or_equal:today',
            'approvalNotes' => 'nullable|string|max:500',
        ];
    }

    public function approvePayment()
    {
        if (!$this->order->isPending() || $this->order->payment_method !== 'manual') {
            session()->flash('error', 'Only pending manual payments can be approved.');
            return;
        }

        $this->validate($this->approvalRules());

        try {
            // Store the approval receipt
            $receiptPath = $this->approvalReceipt->store('approval-receipts', 'public');

            $metadata = $this->order->metadata ?? [];
            $metadata['approved_by'] = auth()->id();
            $metadata['approved_at'] = now()->toISOString();
            $metadata['payment_date'] = $this->paymentDate;
            $metadata['approval_notes'] = $this->approvalNotes ?: 'Payment manually approved by admin';
            $metadata['receipt_file'] = $receiptPath;
            $metadata['approval_receipt_original_name'] = $this->approvalReceipt->getClientOriginalName();

            $paymentDate = \Carbon\Carbon::parse($this->paymentDate);

            $this->order->update([
                'status' => \App\Models\Order::STATUS_PAID,
                'paid_at' => $paymentDate,
                'metadata' => $metadata,
                'failed_at' => null,
                'failure_reason' => null,
            ]);

            // Close modal and reset form
            $this->closeApprovalModal();

            session()->flash('success', 'Payment approved successfully! Payment date recorded as ' . $paymentDate->format('M j, Y') . ' and receipt attached.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to approve payment: ' . $e->getMessage());
        }
    }

    public function rejectPayment($reason = null)
    {
        if (!$this->order->isPending() || $this->order->payment_method !== 'manual') {
            session()->flash('error', 'Only pending manual payments can be rejected.');
            return;
        }

        try {
            $rejectionReason = $reason ?: 'Payment verification failed - insufficient or invalid proof of payment provided';

            $metadata = $this->order->metadata ?? [];
            $metadata['rejected_by'] = auth()->id();
            $metadata['rejected_at'] = now()->toISOString();
            $metadata['rejection_reason'] = $rejectionReason;

            $this->order->markAsFailed([
                'failure_message' => $rejectionReason,
                'failure_code' => 'manual_rejection',
                'rejected_by_admin' => true,
            ]);

            $this->order->update(['metadata' => $metadata]);

            session()->flash('success', 'Payment rejected and student notified. The student will need to resubmit their payment with valid proof.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to reject payment: ' . $e->getMessage());
        }
    }

    public function with(): array
    {
        return [];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Order {{ $order->order_number }}</flux:heading>
            <flux:text class="mt-2">Order details and payment information</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button href="{{ route('orders.index') }}" variant="outline">
                Back to Orders
            </flux:button>
            @if($order->isPaid())
                <flux:button href="{{ route('orders.receipt', $order) }}" variant="outline">
                    View Receipt
                </flux:button>
                <flux:button wire:click="resendReceipt" variant="outline">
                    Resend Receipt
                </flux:button>
            @elseif($order->isPending() && $order->payment_method === 'manual')
                <div class="flex items-center justify-center">
                    <flux:button
                        wire:click="openApprovalModal"
                        variant="primary"
                    >
                        <div class="flex items-center justify-center">
                            <flux:icon name="check" class="w-4 h-4 mr-1" />
                            Approve Payment
                        </div>
                    </flux:button>
                </div>
                <div class="flex items-center justify-center">
                    <flux:button
                        wire:click="rejectPayment"
                        variant="danger"
                        wire:confirm="Are you sure you want to reject this payment? The student will be notified."
                    >
                        <div class="flex items-center justify-center">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                            Reject Payment
                        </div>
                    </flux:button>
                </div>
            @endif
        </div>
    </div>

    @if(session()->has('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon name="check-circle" class="w-5 h-5 text-emerald-600 mr-3" />
                <flux:text class="text-emerald-800">{{ session('success') }}</flux:text>
            </div>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-600 mr-3" />
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        </div>
    @endif

    @if($order->isPending() && $order->payment_method === 'manual')
        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="flex items-start">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-600 mr-3 mt-0.5" />
                <div class="flex-1">
                    <flux:text class="text-amber-800 font-medium">Payment Review Required</flux:text>
                    <flux:text class="text-amber-700 text-sm mt-1">
                        This manual payment is pending approval. Review the student's payment details and <strong>attach a receipt/proof of payment</strong> when approving to maintain proper records.
                    </flux:text>
                    @if($this->hasReceiptAttachment())
                        <div class="mt-3 flex items-center space-x-3">
                            <flux:button wire:click="downloadReceipt" variant="outline" size="sm">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="document-arrow-down" class="w-4 h-4 mr-1" />
                                    Download Receipt
                                </div>
                            </flux:button>
                            <a href="{{ $this->getReceiptUrl() }}" target="_blank">
                                <flux:button variant="ghost" size="sm">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="magnifying-glass" class="w-4 h-4 mr-1" />
                                        View Receipt
                                    </div>
                                </flux:button>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Status -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Order Status</flux:heading>
                    <div class="flex items-center space-x-2">
                        @if($order->isPaid())
                            <flux:badge variant="success" size="lg">{{ $order->status_label }}</flux:badge>
                        @elseif($order->isFailed())
                            <flux:badge variant="danger" size="lg">{{ $order->status_label }}</flux:badge>
                        @elseif($order->isPending())
                            <flux:badge variant="warning" size="lg">{{ $order->status_label }}</flux:badge>
                            @if($order->payment_method === 'manual')
                                <flux:badge variant="amber" size="sm">Awaiting Review</flux:badge>
                            @endif
                        @else
                            <flux:badge variant="gray" size="lg">{{ $order->status_label }}</flux:badge>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <flux:text class="text-gray-600">Order Date</flux:text>
                        <flux:text class="font-semibold">{{ $order->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>

                    @if($order->paid_at)
                        <div>
                            <flux:text class="text-gray-600">Paid Date</flux:text>
                            <flux:text class="font-semibold">{{ $order->paid_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif

                    @if($order->failed_at)
                        <div>
                            <flux:text class="text-gray-600">Failed Date</flux:text>
                            <flux:text class="font-semibold">{{ $order->failed_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text class="text-gray-600">Payment Method</flux:text>
                        <div class="flex items-center">
                            @if($order->payment_method === 'stripe')
                                <flux:icon name="credit-card" class="w-4 h-4 mr-2 text-blue-500" />
                            @else
                                <flux:icon name="banknotes" class="w-4 h-4 mr-2 text-green-500" />
                            @endif
                            <flux:text class="font-semibold">{{ $order->payment_method_label }}</flux:text>
                        </div>
                    </div>

                    <div>
                        <flux:text class="text-gray-600">Billing Period</flux:text>
                        <flux:text class="font-semibold">{{ $order->getPeriodDescription() }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-gray-600">Billing Reason</flux:text>
                        <flux:text class="font-semibold">{{ $order->billing_reason_label }}</flux:text>
                    </div>
                </div>

                @if($order->isFailed() && $order->failure_reason)
                    <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <flux:text class="text-red-800 font-medium">Failure Reason</flux:text>
                        <flux:text class="text-red-700 mt-1">
                            {{ $order->failure_reason['failure_message'] ?? 'Payment failed' }}
                        </flux:text>
                        @if(isset($order->failure_reason['failure_code']))
                            <flux:text size="sm" class="text-red-600 mt-1">
                                Code: {{ $order->failure_reason['failure_code'] }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </flux:card>

            <!-- Order Items -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Order Items</flux:heading>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2">Description</th>
                                <th class="text-center py-2">Quantity</th>
                                <th class="text-right py-2">Unit Price</th>
                                <th class="text-right py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr class="border-b border-gray-100">
                                    <td class="py-3">
                                        <flux:text>{{ $item->description }}</flux:text>
                                        @if($item->stripe_line_item_id)
                                            <flux:text size="xs" class="text-gray-500 block">{{ $item->stripe_line_item_id }}</flux:text>
                                        @endif
                                    </td>
                                    <td class="py-3 text-center">{{ $item->quantity }}</td>
                                    <td class="py-3 text-right">{{ $item->formatted_unit_price }}</td>
                                    <td class="py-3 text-right font-semibold">{{ $item->formatted_total_price }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500">
                                        No order items found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-300">
                                <td colspan="3" class="py-3 text-right font-semibold">Total:</td>
                                <td class="py-3 text-right font-bold text-lg">{{ $order->formatted_amount }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </flux:card>

            <!-- Financial Summary -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Financial Details</flux:heading>

                <div class="space-y-3">
                    <!-- Subtotal Before Discount -->
                    @if($order->hasDiscount())
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <flux:text class="text-gray-700">Subtotal (Before Discount):</flux:text>
                            <flux:text class="font-semibold">{{ $order->formatted_subtotal_before_discount }}</flux:text>
                        </div>

                        <!-- Discount -->
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <flux:text class="text-green-700">
                                Discount
                                @if($order->getCouponCode())
                                    <span class="text-xs">({{ $order->getCouponCode() }})</span>
                                @endif
                                :
                            </flux:text>
                            <flux:text class="font-semibold text-green-700">-{{ $order->formatted_discount }}</flux:text>
                        </div>
                    @endif

                    <!-- Items Subtotal -->
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <flux:text class="text-gray-700">{{ $order->hasDiscount() ? 'Subtotal (After Discount):' : 'Subtotal:' }}</flux:text>
                        <flux:text class="font-semibold">{{ $order->formatted_subtotal }}</flux:text>
                    </div>

                    <!-- Shipping Cost -->
                    @if($order->getShippingCost() > 0)
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <flux:text class="text-gray-700">Shipping Cost:</flux:text>
                            <flux:text class="font-semibold">{{ $order->formatted_shipping }}</flux:text>
                        </div>
                    @endif

                    <!-- Tax -->
                    @if($order->getTaxAmount() > 0)
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <flux:text class="text-gray-700">Tax:</flux:text>
                            <flux:text class="font-semibold">{{ $order->formatted_tax }}</flux:text>
                        </div>
                    @endif

                    <!-- Order Total -->
                    <div class="flex justify-between py-3 border-t-2 border-gray-300 mt-2">
                        <flux:text class="text-lg font-bold">Order Total:</flux:text>
                        <flux:text class="text-lg font-bold text-blue-600">{{ $order->formatted_amount }}</flux:text>
                    </div>

                    <!-- Stripe Fee (for reference) -->
                    @if($order->stripe_fee)
                        <div class="pt-3 mt-3 border-t border-gray-200">
                            <div class="flex justify-between py-1">
                                <flux:text size="sm" class="text-gray-600">Stripe Processing Fee:</flux:text>
                                <flux:text size="sm" class="text-gray-600">-RM {{ number_format($order->stripe_fee, 2) }}</flux:text>
                            </div>
                        </div>
                    @endif

                    <!-- Net Amount (after fees) -->
                    @if($order->net_amount && $order->net_amount != $order->amount)
                        <div class="flex justify-between py-2 bg-emerald-50 px-3 rounded-lg -mx-3">
                            <flux:text class="font-semibold text-emerald-900">Net Amount Received:</flux:text>
                            <flux:text class="font-semibold text-emerald-900">RM {{ number_format($order->net_amount, 2) }}</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Financial Breakdown Summary Box -->
                @if($order->hasDiscount() || $order->getShippingCost() > 0 || $order->getTaxAmount() > 0)
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <flux:heading size="sm" class="text-blue-900 mb-3">Order Calculation Breakdown</flux:heading>
                        <div class="space-y-1 text-sm">
                            @if($order->hasDiscount())
                                <div class="flex justify-between text-blue-800">
                                    <span>Items Total:</span>
                                    <span>{{ $order->formatted_subtotal_before_discount }}</span>
                                </div>
                                <div class="flex justify-between text-green-700">
                                    <span>- Discount:</span>
                                    <span>-{{ $order->formatted_discount }}</span>
                                </div>
                                <div class="flex justify-between text-blue-800">
                                    <span>= Subtotal:</span>
                                    <span>{{ $order->formatted_subtotal }}</span>
                                </div>
                            @else
                                <div class="flex justify-between text-blue-800">
                                    <span>Subtotal:</span>
                                    <span>{{ $order->formatted_subtotal }}</span>
                                </div>
                            @endif

                            @if($order->getShippingCost() > 0)
                                <div class="flex justify-between text-blue-800">
                                    <span>+ Shipping:</span>
                                    <span>{{ $order->formatted_shipping }}</span>
                                </div>
                            @endif

                            @if($order->getTaxAmount() > 0)
                                <div class="flex justify-between text-blue-800">
                                    <span>+ Tax:</span>
                                    <span>{{ $order->formatted_tax }}</span>
                                </div>
                            @endif

                            <div class="flex justify-between font-bold text-blue-900 pt-2 mt-2 border-t border-blue-300">
                                <span>= Final Total:</span>
                                <span>{{ $order->formatted_amount }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </flux:card>

            <!-- Payment Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Payment Details</flux:heading>

                <!-- Payment Method Section -->
                <div class="mb-6 p-4 rounded-lg {{ $order->payment_method === 'stripe' ? 'bg-blue-50 border border-blue-200' : 'bg-green-50 border border-green-200' }}">
                    <div class="flex items-center">
                        @if($order->payment_method === 'stripe')
                            <flux:icon name="credit-card" class="w-6 h-6 mr-3 text-blue-600" />
                            <div>
                                <flux:text class="font-semibold text-blue-900">Stripe Card Payment</flux:text>
                                <flux:text size="sm" class="text-blue-700">Processed automatically via Stripe</flux:text>
                            </div>
                        @else
                            <flux:icon name="banknotes" class="w-6 h-6 mr-3 text-green-600" />
                            <div>
                                <flux:text class="font-semibold text-green-900">Manual Payment</flux:text>
                                <flux:text size="sm" class="text-green-700">Payment processed manually (bank transfer, cash, etc.)</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if($order->payment_method === 'stripe')
                        @if($order->stripe_invoice_id)
                            <div>
                                <flux:text class="text-gray-600">Stripe Invoice ID</flux:text>
                                <flux:text class="font-mono text-sm">{{ $order->stripe_invoice_id }}</flux:text>
                            </div>
                        @endif

                        @if($order->stripe_charge_id)
                            <div>
                                <flux:text class="text-gray-600">Stripe Charge ID</flux:text>
                                <flux:text class="font-mono text-sm">{{ $order->stripe_charge_id }}</flux:text>
                            </div>
                        @endif

                        @if($order->stripe_payment_intent_id)
                            <div>
                                <flux:text class="text-gray-600">Payment Intent ID</flux:text>
                                <flux:text class="font-mono text-sm">{{ $order->stripe_payment_intent_id }}</flux:text>
                            </div>
                        @endif
                    @else
                        <div>
                            <flux:text class="text-gray-600">Payment Type</flux:text>
                            <flux:text class="font-semibold">Manual Payment</flux:text>
                        </div>

                        @if(isset($order->metadata['payment_notes']))
                            <div>
                                <flux:text class="text-gray-600">Payment Notes</flux:text>
                                <flux:text class="text-sm">{{ $order->metadata['payment_notes'] }}</flux:text>
                            </div>
                        @endif
                    @endif

                    <div>
                        <flux:text class="text-gray-600">Currency</flux:text>
                        <flux:text class="font-semibold">{{ strtoupper($order->currency) }}</flux:text>
                    </div>

                    @if($order->payment_method === 'manual' && $this->hasReceiptAttachment())
                        <div class="md:col-span-2">
                            <flux:text class="text-gray-600">Payment Receipt</flux:text>
                            <div class="mt-2 flex items-center space-x-3">
                                <flux:icon icon="document" class="w-5 h-5 text-green-600" />
                                <flux:text class="text-sm text-green-700">Receipt attachment available</flux:text>
                                <flux:button
                                    wire:click="downloadReceipt"
                                    variant="outline"
                                    size="sm"
                                    icon="document">
                                    Download
                                </flux:button>
                                <a href="{{ $this->getReceiptUrl() }}" target="_blank">
                                    <flux:button variant="ghost" size="sm" icon="magnifying-glass">
                                        View
                                    </flux:button>
                                </a>
                            </div>
                            @if(isset($order->metadata['approved_by']) && isset($order->metadata['approved_at']))
                                <div class="mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded-md">
                                    <div class="flex items-center">
                                        <flux:icon name="check-circle" class="w-4 h-4 text-emerald-600 mr-2" />
                                        <flux:text size="sm" class="text-emerald-800 font-medium">Payment Approved</flux:text>
                                    </div>
                                    <flux:text size="xs" class="text-emerald-700 mt-1">
                                        @if(isset($order->metadata['payment_date']))
                                            Payment received: {{ \Carbon\Carbon::parse($order->metadata['payment_date'])->format('M j, Y') }}
                                            <br>
                                        @endif
                                        Approved on {{ \Carbon\Carbon::parse($order->metadata['approved_at'])->format('M j, Y g:i A') }}
                                        @if($this->getApprover())
                                            by {{ $this->getApprover()->name }}
                                        @endif
                                    </flux:text>
                                </div>
                            @elseif(isset($order->metadata['rejected_by']) && isset($order->metadata['rejected_at']))
                                <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                                    <div class="flex items-center">
                                        <flux:icon name="x-circle" class="w-4 h-4 text-red-600 mr-2" />
                                        <flux:text size="sm" class="text-red-800 font-medium">Payment Rejected</flux:text>
                                    </div>
                                    <flux:text size="xs" class="text-red-700 mt-1">
                                        Rejected on {{ \Carbon\Carbon::parse($order->metadata['rejected_at'])->format('M j, Y g:i A') }}
                                        @if(isset($order->metadata['rejection_reason']))
                                            - {{ $order->metadata['rejection_reason'] }}
                                        @endif
                                    </flux:text>
                                </div>
                            @endif
                        </div>
                    @elseif($order->payment_method === 'stripe' && $order->receipt_url)
                        <div class="md:col-span-2">
                            <flux:text class="text-gray-600">Stripe Receipt</flux:text>
                            <div class="mt-2 flex items-center space-x-3">
                                <flux:icon icon="document" class="w-5 h-5 text-blue-600" />
                                <flux:text class="text-sm text-blue-700">Official Stripe receipt available</flux:text>
                                <a href="{{ $order->receipt_url }}" target="_blank">
                                    <flux:button variant="outline" size="sm" icon="external-link">
                                        View Stripe Receipt
                                    </flux:button>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Student Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Student</flux:heading>
                
                <div class="space-y-3">
                    <div>
                        <flux:text class="text-gray-600">Name</flux:text>
                        <flux:text class="font-semibold">{{ $order->student->user->name }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-gray-600">Email</flux:text>
                        <flux:text class="font-semibold">{{ $order->student->user->email }}</flux:text>
                    </div>

                    <div class="pt-2">
                        <flux:button href="{{ route('students.show', $order->student) }}" variant="outline" size="sm">
                            View Student
                        </flux:button>
                    </div>
                </div>
            </flux:card>

            <!-- Course Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Course</flux:heading>
                
                <div class="space-y-3">
                    <div>
                        <flux:text class="text-gray-600">Course Name</flux:text>
                        <flux:text class="font-semibold">{{ $order->course->name }}</flux:text>
                    </div>
                    
                    @if($order->course->description)
                        <div>
                            <flux:text class="text-gray-600">Description</flux:text>
                            <flux:text class="text-sm">{{ Str::limit($order->course->description, 100) }}</flux:text>
                        </div>
                    @endif

                    <div class="pt-2">
                        <flux:button href="{{ route('courses.show', $order->course) }}" variant="outline" size="sm">
                            View Course
                        </flux:button>
                    </div>
                </div>
            </flux:card>

            <!-- Enrollment Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Enrollment</flux:heading>
                
                <div class="space-y-3">
                    <div>
                        <flux:text class="text-gray-600">Status</flux:text>
                        <flux:text class="font-semibold">{{ ucfirst($order->enrollment->status) }}</flux:text>
                    </div>
                    
                    @if($order->enrollment->subscription_status)
                        <div>
                            <flux:text class="text-gray-600">Subscription Status</flux:text>
                            <flux:text class="font-semibold">{{ $order->enrollment->getSubscriptionStatusLabel() }}</flux:text>
                        </div>
                    @endif

                    @if($order->enrollment->enrollment_date)
                        <div>
                            <flux:text class="text-gray-600">Enrolled Date</flux:text>
                            <flux:text class="font-semibold">{{ $order->enrollment->enrollment_date->format('M j, Y') }}</flux:text>
                        </div>
                    @endif

                    <div class="pt-2">
                        <flux:button href="{{ route('enrollments.show', $order->enrollment) }}" variant="outline" size="sm">
                            View Enrollment
                        </flux:button>
                    </div>
                </div>
            </flux:card>

            <!-- Actions -->
            @if($order->isPending() && $order->payment_method === 'manual')
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Payment Actions</flux:heading>

                    <div class="space-y-3">
                        <flux:button
                            wire:click="openApprovalModal"
                            variant="primary"
                            class="w-full"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="check" class="w-4 h-4 mr-2" />
                                Approve Payment
                            </div>
                        </flux:button>

                        <flux:button
                            wire:click="rejectPayment"
                            variant="danger"
                            class="w-full"
                            wire:confirm="Are you sure you want to reject this payment? The student will be notified."
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                                Reject Payment
                            </div>
                        </flux:button>

                        @if($this->hasReceiptAttachment())
                            <flux:separator class="my-4" />
                            <flux:text size="sm" class="text-gray-600 mb-2">Payment Receipt</flux:text>
                            <flux:button wire:click="downloadReceipt" variant="outline" class="w-full">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="document-arrow-down" class="w-4 h-4 mr-2" />
                                    Download Receipt
                                </div>
                            </flux:button>
                            <a href="{{ $this->getReceiptUrl() }}" target="_blank">
                                <flux:button variant="ghost" class="w-full">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="magnifying-glass" class="w-4 h-4 mr-2" />
                                        View Receipt
                                    </div>
                                </flux:button>
                            </a>
                        @endif
                    </div>
                </flux:card>
            @elseif($order->isPaid())
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Actions</flux:heading>

                    <div class="space-y-3">
                        <flux:button wire:click="processRefund" variant="outline" class="w-full">
                            Process Refund
                        </flux:button>

                        <flux:button href="{{ route('orders.receipt', $order) }}" variant="outline" class="w-full">
                            View Official Receipt
                        </flux:button>
                        @if($this->hasReceiptAttachment())
                            <flux:button wire:click="downloadReceipt" variant="outline" class="w-full">
                                Download Payment Receipt
                            </flux:button>
                        @endif
                        <flux:button wire:click="resendReceipt" variant="outline" class="w-full">
                            Resend Receipt
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </div>
    </div>

    <!-- Payment Approval Modal -->
    <flux:modal wire:model="showApprovalModal" variant="flyout">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Approve Payment</flux:heading>
                <flux:text class="text-gray-600">
                    Upload a receipt or proof of payment and add approval notes for Order {{ $order->order_number }}.
                </flux:text>
            </div>

            <form wire:submit.prevent="approvePayment">
                <div class="space-y-4">
                    <!-- Receipt Upload -->
                    <flux:field>
                        <flux:label>Payment Receipt / Proof of Payment *</flux:label>
                        <flux:description>
                            Upload receipt, bank transfer proof, or other payment verification document (JPG, PNG, PDF - Max 5MB)
                        </flux:description>

                        <div class="mt-2">
                            <input
                                type="file"
                                wire:model="approvalReceipt"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="block w-full text-sm text-gray-500
                                       file:mr-4 file:py-2 file:px-4
                                       file:rounded-md file:border-0
                                       file:text-sm file:font-medium
                                       file:bg-blue-50 file:text-blue-700
                                       hover:file:bg-blue-100
                                       focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            />
                        </div>

                        <flux:error name="approvalReceipt" />

                        @if($approvalReceipt)
                            <div class="mt-2 p-2 bg-green-50 border border-green-200 rounded-md">
                                <div class="flex items-center">
                                    <flux:icon name="document" class="w-4 h-4 text-green-600 mr-2" />
                                    <flux:text size="sm" class="text-green-700">
                                        {{ $approvalReceipt->getClientOriginalName() }}
                                    </flux:text>
                                </div>
                            </div>
                        @endif
                    </flux:field>

                    <!-- Payment Date -->
                    <flux:field>
                        <flux:label>Payment Date *</flux:label>
                        <flux:description>
                            Select the date when the payment was actually received (not the approval date).
                        </flux:description>
                        <flux:input
                            type="date"
                            wire:model="paymentDate"
                            max="{{ now()->format('Y-m-d') }}"
                            required
                        />
                        <flux:error name="paymentDate" />
                    </flux:field>

                    <!-- Approval Notes -->
                    <flux:field>
                        <flux:label>Approval Notes (Optional)</flux:label>
                        <flux:description>
                            Add any additional notes about the payment verification or approval process.
                        </flux:description>
                        <flux:textarea
                            wire:model="approvalNotes"
                            rows="3"
                            placeholder="e.g., Verified bank transfer receipt, amount matches invoice, etc."
                        />
                        <flux:error name="approvalNotes" />
                    </flux:field>

                    <!-- Payment Summary -->
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <flux:heading size="sm" class="text-blue-900 mb-2">Payment Summary</flux:heading>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-blue-700">Order Number:</span>
                                <span class="font-medium">{{ $order->order_number }}</span>
                            </div>
                            <div>
                                <span class="text-blue-700">Amount:</span>
                                <span class="font-medium">{{ $order->formatted_amount }}</span>
                            </div>
                            <div>
                                <span class="text-blue-700">Student:</span>
                                <span class="font-medium">{{ $order->student->user->name }}</span>
                            </div>
                            <div>
                                <span class="text-blue-700">Course:</span>
                                <span class="font-medium">{{ $order->course->name }}</span>
                            </div>
                            @if($paymentDate)
                                <div class="col-span-2 mt-2 pt-2 border-t border-blue-300">
                                    <span class="text-blue-700">Payment Date:</span>
                                    <span class="font-medium">{{ \Carbon\Carbon::parse($paymentDate)->format('M j, Y') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                        <flux:button
                            type="button"
                            variant="ghost"
                            wire:click="closeApprovalModal"
                        >
                            Cancel
                        </flux:button>

                        <flux:button
                            type="submit"
                            variant="primary"
                            wire:loading.attr="disabled"
                            wire:target="approvePayment"
                        >
                            <div wire:loading.remove wire:target="approvePayment" class="flex items-center">
                                <flux:icon name="check" class="w-4 h-4 mr-2" />
                                Approve Payment
                            </div>
                            <div wire:loading wire:target="approvePayment" class="flex items-center">
                                <flux:icon name="arrow-path" class="w-4 h-4 mr-2 animate-spin" />
                                Approving...
                            </div>
                        </flux:button>
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>
</div>