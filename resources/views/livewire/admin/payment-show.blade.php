<?php
use App\Models\Payment;
use App\Services\StripeService;
use App\Mail\PaymentConfirmation;
use App\Mail\PaymentFailed;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    public Payment $payment;
    public bool $isProcessing = false;

    public function mount(Payment $payment)
    {
        // Ensure user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        $this->payment = $payment->load(['user', 'invoice.course', 'paymentMethod']);
    }

    public function refundPayment()
    {
        if (!$this->payment->canBeRefunded()) {
            session()->flash('error', 'This payment cannot be refunded.');
            return;
        }

        $this->isProcessing = true;

        try {
            $stripeService = app(StripeService::class);
            
            if ($this->payment->isStripePayment()) {
                // Process Stripe refund
                $refund = $stripeService->refundPayment($this->payment->stripe_payment_intent_id);
                
                if ($refund) {
                    $this->payment->update([
                        'status' => Payment::STATUS_REFUNDED,
                        'stripe_refund_id' => $refund->id,
                        'notes' => ($this->payment->notes ?: '') . "\nRefunded by admin: " . auth()->user()->name . ' at ' . now()->format('Y-m-d H:i:s')
                    ]);

                    session()->flash('success', 'Payment refunded successfully.');
                } else {
                    session()->flash('error', 'Failed to process refund with Stripe.');
                }
            } else {
                // Mark bank transfer as refunded
                $this->payment->update([
                    'status' => Payment::STATUS_REFUNDED,
                    'notes' => ($this->payment->notes ?: '') . "\nMarked as refunded by admin: " . auth()->user()->name . ' at ' . now()->format('Y-m-d H:i:s')
                ]);

                session()->flash('success', 'Payment marked as refunded.');
            }

            $this->payment->refresh();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to refund payment: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function approvePayment()
    {
        if (!$this->payment->isBankTransfer() || !$this->payment->isPending()) {
            session()->flash('error', 'This payment cannot be approved.');
            return;
        }

        $this->isProcessing = true;

        try {
            $this->payment->update([
                'status' => Payment::STATUS_SUCCEEDED,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'notes' => ($this->payment->notes ?: '') . "\nApproved by admin: " . auth()->user()->name . ' at ' . now()->format('Y-m-d H:i:s')
            ]);

            // Mark invoice as paid if this payment covers the full amount
            $invoice = $this->payment->invoice;
            $totalPaidAmount = $invoice->payments()->successful()->sum('amount');
            
            if ($totalPaidAmount >= $invoice->amount) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now()
                ]);
            }

            // Send payment confirmation email
            try {
                Mail::to($this->payment->user->email)->send(new PaymentConfirmation($this->payment));
            } catch (\Exception $e) {
                // Log the error but don't fail the approval
                \Log::error('Failed to send payment confirmation email', [
                    'payment_id' => $this->payment->id,
                    'error' => $e->getMessage()
                ]);
            }

            session()->flash('success', 'Payment approved successfully.');
            $this->payment->refresh();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to approve payment: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function rejectPayment()
    {
        if (!$this->payment->isBankTransfer() || !$this->payment->isPending()) {
            session()->flash('error', 'This payment cannot be rejected.');
            return;
        }

        $this->isProcessing = true;

        try {
            $this->payment->update([
                'status' => Payment::STATUS_FAILED,
                'notes' => ($this->payment->notes ?: '') . "\nRejected by admin: " . auth()->user()->name . ' at ' . now()->format('Y-m-d H:i:s')
            ]);

            // Send payment failed email
            try {
                Mail::to($this->payment->user->email)->send(new PaymentFailed($this->payment));
            } catch (\Exception $e) {
                // Log the error but don't fail the rejection
                \Log::error('Failed to send payment failed email', [
                    'payment_id' => $this->payment->id,
                    'error' => $e->getMessage()
                ]);
            }

            session()->flash('success', 'Payment rejected.');
            $this->payment->refresh();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to reject payment: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function getStatusBadgeColor($status): string
    {
        return match($status) {
            Payment::STATUS_SUCCEEDED => 'emerald',
            Payment::STATUS_FAILED, Payment::STATUS_CANCELLED => 'red',
            Payment::STATUS_PROCESSING => 'blue',
            Payment::STATUS_PENDING => 'amber',
            Payment::STATUS_REQUIRES_ACTION, Payment::STATUS_REQUIRES_PAYMENT_METHOD => 'orange',
            Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED => 'purple',
            default => 'gray'
        };
    }

    public function with(): array
    {
        return [
            'canRefund' => $this->payment->canBeRefunded(),
            'canApprove' => $this->payment->isBankTransfer() && $this->payment->isPending(),
            'canReject' => $this->payment->isBankTransfer() && $this->payment->isPending(),
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Payment Details</flux:heading>
            <flux:text class="mt-2">Manage payment transaction #{{ $payment->id }}</flux:text>
        </div>
        <flux:button variant="outline" icon="arrow-left" href="{{ route('admin.payments') }}" wire:navigate>
            Back to Payments
        </flux:button>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon icon="check-circle" class="w-5 h-5 text-emerald-600 mr-3" />
                <flux:text class="text-emerald-800">{{ session('success') }}</flux:text>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon icon="exclamation-circle" class="w-5 h-5 text-red-600 mr-3" />
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Payment Information -->
        <div class="lg:col-span-2">
            <flux:card>
                <flux:header>
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">Payment Information</flux:heading>
                        <flux:badge :color="$this->getStatusBadgeColor($payment->status)">
                            {{ $payment->status_label }}
                        </flux:badge>
                    </div>
                </flux:header>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <flux:text size="sm" class="text-gray-600">Payment ID</flux:text>
                            <flux:text class="font-medium">{{ $payment->id }}</flux:text>
                        </div>

                        <div>
                            <flux:text size="sm" class="text-gray-600">Amount</flux:text>
                            <flux:heading size="md">{{ $payment->formatted_amount }}</flux:heading>
                        </div>

                        <div>
                            <flux:text size="sm" class="text-gray-600">Payment Method</flux:text>
                            <div class="flex items-center space-x-2">
                                <flux:badge color="{{ $payment->isStripePayment() ? 'blue' : 'green' }}" size="sm">
                                    {{ $payment->type_label }}
                                </flux:badge>
                                @if($payment->isStripePayment() && $payment->paymentMethod)
                                    <flux:text class="text-sm">
                                        **** {{ $payment->paymentMethod->card_details['last4'] ?? '****' }}
                                    </flux:text>
                                @endif
                            </div>
                        </div>

                        <div>
                            <flux:text size="sm" class="text-gray-600">Created</flux:text>
                            <flux:text class="font-medium">{{ $payment->created_at->format('M d, Y H:i') }}</flux:text>
                            <flux:text size="sm" class="text-gray-500">{{ $payment->created_at->diffForHumans() }}</flux:text>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @if($payment->stripe_payment_intent_id)
                            <div>
                                <flux:text size="sm" class="text-gray-600">Stripe Payment Intent</flux:text>
                                <flux:text class="font-mono text-sm">{{ $payment->stripe_payment_intent_id }}</flux:text>
                            </div>
                        @endif

                        @if($payment->stripe_fee > 0)
                            <div>
                                <flux:text size="sm" class="text-gray-600">Stripe Fee</flux:text>
                                <flux:text class="font-medium text-red-600">RM {{ number_format($payment->stripe_fee, 2) }}</flux:text>
                            </div>
                        @endif

                        @if($payment->stripe_refund_id)
                            <div>
                                <flux:text size="sm" class="text-gray-600">Refund ID</flux:text>
                                <flux:text class="font-mono text-sm">{{ $payment->stripe_refund_id }}</flux:text>
                            </div>
                        @endif

                        @if($payment->approved_at)
                            <div>
                                <flux:text size="sm" class="text-gray-600">Approved</flux:text>
                                <flux:text class="font-medium">{{ $payment->approved_at->format('M d, Y H:i') }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>

                @if($payment->notes)
                    <div class="mt-6 border-t pt-6">
                        <flux:text size="sm" class="text-gray-600 mb-2">Notes</flux:text>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                            <flux:text class="whitespace-pre-wrap">{{ $payment->notes }}</flux:text>
                        </div>
                    </div>
                @endif
            </flux:card>

            <!-- Bank Transfer Details -->
            @if($payment->isBankTransfer() && $payment->stripe_metadata)
                <flux:card class="mt-6">
                    <flux:header>
                        <flux:heading size="lg">Bank Transfer Details</flux:heading>
                    </flux:header>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if(isset($payment->stripe_metadata['transaction_reference']))
                            <div>
                                <flux:text size="sm" class="text-gray-600">Transaction Reference</flux:text>
                                <flux:text class="font-mono font-medium">{{ $payment->stripe_metadata['transaction_reference'] }}</flux:text>
                            </div>
                        @endif
                        
                        @if(isset($payment->stripe_metadata['payment_date']))
                            <div>
                                <flux:text size="sm" class="text-gray-600">Payment Date</flux:text>
                                <flux:text class="font-medium">{{ \Carbon\Carbon::parse($payment->stripe_metadata['payment_date'])->format('M d, Y') }}</flux:text>
                            </div>
                        @endif
                        
                        @if(isset($payment->stripe_metadata['submitted_at']))
                            <div>
                                <flux:text size="sm" class="text-gray-600">Submitted At</flux:text>
                                <flux:text class="font-medium">{{ \Carbon\Carbon::parse($payment->stripe_metadata['submitted_at'])->format('M d, Y H:i') }}</flux:text>
                            </div>
                        @endif
                        
                        @if(isset($payment->stripe_metadata['type']))
                            <div>
                                <flux:text size="sm" class="text-gray-600">Transfer Type</flux:text>
                                <flux:text class="font-medium">{{ ucwords(str_replace('_', ' ', $payment->stripe_metadata['type'])) }}</flux:text>
                            </div>
                        @endif
                    </div>

                    @if(isset($payment->stripe_metadata['student_notes']) && $payment->stripe_metadata['student_notes'])
                        <div class="mt-4">
                            <flux:text size="sm" class="text-gray-600 mb-2">Student Notes</flux:text>
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                <flux:text class="text-sm">{{ $payment->stripe_metadata['student_notes'] }}</flux:text>
                            </div>
                        </div>
                    @endif

                    @if(isset($payment->stripe_metadata['proof_file_path']))
                        <div class="mt-6 border-t pt-6">
                            <flux:text size="sm" class="text-gray-600 mb-4">Proof of Payment</flux:text>
                            <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
                                @php
                                    $filePath = $payment->stripe_metadata['proof_file_path'];
                                    $fileUrl = Storage::disk('public')->url($filePath);
                                    $isImage = in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']);
                                    $isPdf = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf';
                                @endphp
                                
                                @if($isImage)
                                    <div class="text-center">
                                        <img src="{{ $fileUrl }}" alt="Proof of Payment" class="max-w-full h-auto rounded-lg border shadow-sm mx-auto" style="max-height: 500px;">
                                        <div class="mt-3">
                                            <flux:button variant="outline" size="sm" icon="arrow-top-right-on-square" onclick="window.open('{{ $fileUrl }}', '_blank')">
                                                View Full Size
                                            </flux:button>
                                        </div>
                                    </div>
                                @elseif($isPdf)
                                    <div class="text-center py-8">
                                        <flux:icon icon="document-text" class="w-16 h-16 text-red-500 mx-auto mb-4" />
                                        <flux:text class="font-medium mb-2">PDF Document</flux:text>
                                        <flux:text size="sm" class="text-gray-600 mb-4">Click below to view the PDF proof of payment</flux:text>
                                        <flux:button variant="filled" color="red" icon="document-text" onclick="window.open('{{ $fileUrl }}', '_blank')">
                                            View PDF
                                        </flux:button>
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <flux:icon icon="document" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                                        <flux:text class="font-medium mb-2">Uploaded File</flux:text>
                                        <flux:text size="sm" class="text-gray-600 mb-4">{{ basename($filePath) }}</flux:text>
                                        <flux:button variant="outline" icon="arrow-down-tray" onclick="window.open('{{ $fileUrl }}', '_blank')">
                                            Download File
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endif
        </div>

        <!-- Actions and Related Information -->
        <div>
            <!-- Quick Actions -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Actions</flux:heading>
                </flux:header>

                <div class="space-y-3">
                    @if($canApprove)
                        <flux:button 
                            variant="filled" 
                            color="emerald" 
                            class="w-full" 
                            :icon="$isProcessing ? 'arrow-path' : 'check'"
                            wire:click="approvePayment"
                            wire:confirm="Are you sure you want to approve this payment? This action cannot be undone."
                            :disabled="$isProcessing"
                        >
                            @if($isProcessing)
                                Processing...
                            @else
                                Approve Payment
                            @endif
                        </flux:button>
                    @endif

                    @if($canReject)
                        <flux:button 
                            variant="filled" 
                            color="red" 
                            class="w-full" 
                            :icon="$isProcessing ? 'arrow-path' : 'x-mark'"
                            wire:click="rejectPayment"
                            wire:confirm="Are you sure you want to reject this payment? This action cannot be undone."
                            :disabled="$isProcessing"
                        >
                            @if($isProcessing)
                                Processing...
                            @else
                                Reject Payment
                            @endif
                        </flux:button>
                    @endif

                    @if($canRefund)
                        <flux:button 
                            variant="filled" 
                            color="amber" 
                            class="w-full" 
                            icon="arrow-path"
                            wire:click="refundPayment"
                            wire:confirm="Are you sure you want to refund this payment? This will process a refund through the original payment method."
                            :disabled="$isProcessing"
                        >
                            @if($isProcessing)
                                Processing...
                            @else
                                Refund Payment
                            @endif
                        </flux:button>
                    @endif

                    <flux:button 
                        variant="outline" 
                        class="w-full" 
                        icon="document-text"
                        :href="route('invoices.show', $payment->invoice)" 
                        wire:navigate
                    >
                        View Invoice
                    </flux:button>
                </div>
            </flux:card>

            <!-- Student Information -->
            <flux:card class="mt-6">
                <flux:header>
                    <flux:heading size="lg">Student</flux:heading>
                </flux:header>

                <div class="space-y-4">
                    <div>
                        <flux:text class="font-medium">{{ $payment->user->name }}</flux:text>
                        <flux:text size="sm" class="text-gray-600 block">{{ $payment->user->email }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Student ID</flux:text>
                        <flux:text class="font-medium">{{ $payment->user->student_id }}</flux:text>
                    </div>

                    <flux:button variant="outline" size="sm" icon="user" class="w-full">
                        View Student Profile
                    </flux:button>
                </div>
            </flux:card>

            <!-- Invoice Information -->
            <flux:card class="mt-6">
                <flux:header>
                    <flux:heading size="lg">Invoice</flux:heading>
                </flux:header>

                <div class="space-y-4">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Invoice Number</flux:text>
                        <flux:text class="font-medium">{{ $payment->invoice->invoice_number }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Course</flux:text>
                        <flux:text class="font-medium">{{ $payment->invoice->course->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Invoice Amount</flux:text>
                        <flux:text class="font-medium">{{ $payment->invoice->formatted_amount }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Due Date</flux:text>
                        <flux:text class="font-medium {{ $payment->invoice->isOverdue() ? 'text-red-600' : '' }}">
                            {{ $payment->invoice->due_date->format('M d, Y') }}
                        </flux:text>
                    </div>

                    <flux:badge :color="$payment->invoice->isPaid() ? 'emerald' : ($payment->invoice->isOverdue() ? 'red' : 'amber')" size="sm">
                        {{ $payment->invoice->status_label }}
                    </flux:badge>
                </div>
            </flux:card>
        </div>
    </div>
</div>