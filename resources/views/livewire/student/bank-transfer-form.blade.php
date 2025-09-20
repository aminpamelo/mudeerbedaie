<?php
use App\Models\Invoice;
use App\Models\Payment;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public Invoice $invoice;
    public $proofOfPayment;
    public string $transactionReference = '';
    public string $paymentDate = '';
    public string $notes = '';
    
    public $bankDetails;
    public bool $isSubmitting = false;

    public function mount(Invoice $invoice)
    {
        // Ensure user can pay this invoice
        if (!auth()->user()->isStudent() || $invoice->student_id !== auth()->id()) {
            abort(403, 'Access denied');
        }

        // Check if invoice can be paid
        if ($invoice->isPaid()) {
            session()->flash('error', 'This invoice has already been paid.');
            return redirect()->route('student.invoices');
        }

        if (!$invoice->canCreatePayment()) {
            session()->flash('error', 'This invoice cannot be paid at this time.');
            return redirect()->route('student.invoices');
        }

        $this->invoice = $invoice->load(['course']);
        $this->paymentDate = now()->format('Y-m-d');
        
        // Load bank details from settings
        $this->bankDetails = [
            'bank_name' => setting('bank_name', ''),
            'account_name' => setting('bank_account_name', ''),
            'account_number' => setting('bank_account_number', ''),
            'swift_code' => setting('bank_swift_code', ''),
        ];
    }

    public function rules(): array
    {
        return [
            'proofOfPayment' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
            'transactionReference' => 'required|string|max:255',
            'paymentDate' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000'
        ];
    }

    public function submitPayment()
    {
        $this->validate();

        $this->isSubmitting = true;

        try {
            // Store the uploaded file
            $filePath = $this->proofOfPayment->store('payment-proofs', 'public');

            // Create the payment record
            $payment = $this->invoice->createBankTransferPayment();
            
            // Update payment with bank transfer details
            $payment->update([
                'notes' => $this->buildPaymentNotes(),
                'stripe_metadata' => [
                    'type' => 'bank_transfer',
                    'transaction_reference' => $this->transactionReference,
                    'payment_date' => $this->paymentDate,
                    'proof_file_path' => $filePath,
                    'submitted_at' => now()->toISOString(),
                    'student_notes' => $this->notes,
                ]
            ]);

            // Clear form
            $this->reset(['proofOfPayment', 'transactionReference', 'notes']);
            $this->paymentDate = now()->format('Y-m-d');

            session()->flash('success', 'Bank transfer details submitted successfully! We will verify your payment and update the invoice status within 1-2 business days.');
            
            return redirect()->route('student.invoices.show', $this->invoice);

        } catch (\Exception $e) {
            $this->isSubmitting = false;
            session()->flash('error', 'Failed to submit payment: ' . $e->getMessage());
        }
    }

    private function buildPaymentNotes(): string
    {
        $notes ="Bank Transfer Payment Submission:\n";
        $notes .="Transaction Reference: {$this->transactionReference}\n";
        $notes .="Payment Date: {$this->paymentDate}\n";
        $notes .="Submitted: " . now()->format('Y-m-d H:i:s') . "\n";
        
        if ($this->notes) {
            $notes .="Student Notes: {$this->notes}\n";
        }
        
        $notes .="Status: Awaiting admin verification\n";
        
        return $notes;
    }

    public function with(): array
    {
        return [
            'hasBankDetails' => !empty($this->bankDetails['bank_name']),
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Bank Transfer Payment</flux:heading>
            <flux:text class="mt-2">Submit proof of bank transfer for Invoice {{ $invoice->invoice_number }}</flux:text>
        </div>
        <flux:button variant="outline" icon="arrow-left" href="{{ route('student.invoices.pay', $invoice) }}" wire:navigate>
            Back to Payment
        </flux:button>
    </div>

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
        <!-- Bank Transfer Form -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Bank Details -->
            @if($hasBankDetails)
                <flux:card>
                    <flux:header>
                        <flux:heading size="lg">Bank Account Details</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Transfer the exact invoice amount to this account</flux:text>
                    </flux:header>

                    <div class="bg-blue-50 /20 p-4 rounded-lg">
                        <div class="grid grid-cols-1 gap-4">
                            @if($bankDetails['bank_name'])
                                <div>
                                    <flux:text size="sm" class="text-gray-600 font-medium">Bank Name</flux:text>
                                    <flux:text class="font-mono text-lg">{{ $bankDetails['bank_name'] }}</flux:text>
                                </div>
                            @endif

                            @if($bankDetails['account_name'])
                                <div>
                                    <flux:text size="sm" class="text-gray-600 font-medium">Account Holder Name</flux:text>
                                    <flux:text class="font-mono text-lg">{{ $bankDetails['account_name'] }}</flux:text>
                                </div>
                            @endif

                            @if($bankDetails['account_number'])
                                <div>
                                    <flux:text size="sm" class="text-gray-600 font-medium">Account Number</flux:text>
                                    <flux:text class="font-mono text-lg">{{ $bankDetails['account_number'] }}</flux:text>
                                </div>
                            @endif

                            @if($bankDetails['swift_code'])
                                <div>
                                    <flux:text size="sm" class="text-gray-600 font-medium">SWIFT/BIC Code</flux:text>
                                    <flux:text class="font-mono text-lg">{{ $bankDetails['swift_code'] }}</flux:text>
                                </div>
                            @endif

                            <div class="border-t pt-4">
                                <flux:text size="sm" class="text-gray-600 font-medium">Transfer Amount</flux:text>
                                <flux:heading size="xl" class="text-blue-600">{{ $invoice->formatted_amount }}</flux:heading>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-amber-50 /20 border border-amber-200 rounded-lg">
                        <div class="flex items-start">
                            <flux:icon icon="exclamation-triangle" class="w-5 h-5 text-amber-600 mr-3 mt-0.5" />
                            <div>
                                <flux:text class="font-medium text-amber-800">Important Instructions:</flux:text>
                                <ul class="mt-2 text-sm text-amber-700 space-y-1">
                                    <li>• Transfer the exact amount: {{ $invoice->formatted_amount }}</li>
                                    <li>• Include the invoice number {{ $invoice->invoice_number }} as reference</li>
                                    <li>• Keep your transaction receipt for upload</li>
                                    <li>• Payment verification may take 1-2 business days</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </flux:card>
            @else
                <flux:card>
                    <div class="text-center py-8">
                        <flux:icon icon="exclamation-circle" class="w-12 h-12 text-amber-500 mx-auto mb-4" />
                        <flux:heading size="md" class="text-amber-600 mb-2">Bank Details Not Available</flux:heading>
                        <flux:text class="text-gray-600 mb-4">
                            Bank transfer details have not been configured yet. Please contact the administrator or use card payment instead.
                        </flux:text>
                        <flux:button variant="outline" href="{{ route('student.invoices.pay', $invoice) }}" wire:navigate>
                            Try Card Payment Instead
                        </flux:button>
                    </div>
                </flux:card>
            @endif

            <!-- Payment Submission Form -->
            @if($hasBankDetails)
                <flux:card>
                    <flux:header>
                        <flux:heading size="lg">Submit Payment Proof</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Upload your transaction receipt and details</flux:text>
                    </flux:header>

                    <form wire:submit="submitPayment" class="space-y-6">
                        <!-- File Upload -->
                        <flux:field>
                            <flux:label>Proof of Payment *</flux:label>
                            <flux:description>
                                Upload your bank transfer receipt, screenshot, or transaction confirmation. 
                                Accepted formats: JPG, PNG, PDF. Max size: 5MB.
                            </flux:description>
                            <flux:input type="file" wire:model="proofOfPayment" accept=".jpg,.jpeg,.png,.pdf" />
                            <flux:error name="proofOfPayment" />
                            
                            @if($proofOfPayment && !$errors->has('proofOfPayment'))
                                <div class="mt-2 p-2 bg-emerald-50 border border-emerald-200 rounded">
                                    <flux:text size="sm" class="text-emerald-800">
                                        <flux:icon icon="check" class="w-4 h-4 inline mr-1" />
                                        File selected: {{ $proofOfPayment->getClientOriginalName() }}
                                    </flux:text>
                                </div>
                            @endif
                        </flux:field>

                        <!-- Transaction Reference -->
                        <flux:field>
                            <flux:label>Transaction Reference Number *</flux:label>
                            <flux:description>
                                Enter the reference number from your bank transfer receipt
                            </flux:description>
                            <flux:input 
                                wire:model="transactionReference" 
                                placeholder="e.g. TXN123456789 or REF-2025-001234"
                            />
                            <flux:error name="transactionReference" />
                        </flux:field>

                        <!-- Payment Date -->
                        <flux:field>
                            <flux:label>Payment Date *</flux:label>
                            <flux:description>
                                When did you make the bank transfer?
                            </flux:description>
                            <flux:input type="date" wire:model="paymentDate" max="{{ date('Y-m-d') }}" />
                            <flux:error name="paymentDate" />
                        </flux:field>

                        <!-- Additional Notes -->
                        <flux:field>
                            <flux:label>Additional Notes</flux:label>
                            <flux:description>
                                Any additional information about your payment (optional)
                            </flux:description>
                            <flux:textarea 
                                wire:model="notes" 
                                placeholder="e.g. Transferred from my business account, used different reference format, etc."
                                rows="3"
                            />
                            <flux:error name="notes" />
                        </flux:field>

                        <!-- Submit Button -->
                        <div class="border-t pt-6">
                            <flux:button 
                                type="submit" 
                                variant="filled" 
                                color="emerald" 
                                size="lg" 
                                class="w-full"
                                :icon="$isSubmitting ? 'arrow-path' : 'cloud-arrow-up'"
                                :disabled="$isSubmitting"
                            >
                                @if($isSubmitting)
                                    Submitting...
                                @else
                                    Submit Payment Proof
                                @endif
                            </flux:button>
                        </div>
                    </form>
                </flux:card>
            @endif
        </div>

        <!-- Invoice Summary Sidebar -->
        <div class="space-y-6">
            <!-- Invoice Details -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Invoice Details</flux:heading>
                </flux:header>

                <div class="space-y-4">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Invoice Number</flux:text>
                        <flux:text class="font-medium">{{ $invoice->invoice_number }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Course</flux:text>
                        <flux:text class="font-medium">{{ $invoice->course->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Due Date</flux:text>
                        <flux:text class="font-medium {{ $invoice->isOverdue() ? 'text-red-600' : '' }}">
                            {{ $invoice->due_date->format('M d, Y') }}
                            @if($invoice->isOverdue())
                                <flux:badge color="red" size="sm" class="ml-2">Overdue</flux:badge>
                            @endif
                        </flux:text>
                    </div>

                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center">
                            <flux:text class="font-medium">Amount to Transfer</flux:text>
                            <flux:heading size="lg" class="text-emerald-600">{{ $invoice->formatted_amount }}</flux:heading>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Process Timeline -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Verification Process</flux:heading>
                </flux:header>

                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center">
                            <flux:icon icon="credit-card" class="w-4 h-4" />
                        </div>
                        <div>
                            <flux:text class="font-medium">1. Make Bank Transfer</flux:text>
                            <flux:text size="sm" class="text-gray-600">Transfer exact amount to our account</flux:text>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center">
                            <flux:icon icon="cloud-arrow-up" class="w-4 h-4" />
                        </div>
                        <div>
                            <flux:text class="font-medium">2. Upload Proof</flux:text>
                            <flux:text size="sm" class="text-gray-600">Submit receipt and transaction details</flux:text>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gray-300 text-white rounded-full flex items-center justify-center">
                            <flux:icon icon="eye" class="w-4 h-4" />
                        </div>
                        <div>
                            <flux:text class="font-medium">3. Admin Verification</flux:text>
                            <flux:text size="sm" class="text-gray-600">We'll verify your payment (1-2 days)</flux:text>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gray-300 text-white rounded-full flex items-center justify-center">
                            <flux:icon icon="check" class="w-4 h-4" />
                        </div>
                        <div>
                            <flux:text class="font-medium">4. Invoice Marked Paid</flux:text>
                            <flux:text size="sm" class="text-gray-600">You'll receive confirmation email</flux:text>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Help & Support -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Need Help?</flux:heading>
                </flux:header>

                <div class="space-y-3">
                    <flux:text size="sm">If you have any issues with your bank transfer or need assistance:</flux:text>
                    
                    <div class="space-y-2">
                        <div class="flex items-center text-sm">
                            <flux:icon icon="envelope" class="w-4 h-4 mr-2 text-gray-500" />
                            <span>Email: {{ setting('admin_email', 'support@example.com') }}</span>
                        </div>
                        
                        <div class="flex items-center text-sm">
                            <flux:icon icon="clock" class="w-4 h-4 mr-2 text-gray-500" />
                            <span>Response time: 1-2 business days</span>
                        </div>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>