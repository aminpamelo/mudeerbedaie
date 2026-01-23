<?php

use App\Models\ReturnRefund;
use App\Models\ProductOrder;
use App\Models\Package;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    // Form fields
    public ?int $orderId = null;
    public ?int $packageId = null;
    public ?int $customerId = null;
    public string $returnDate = '';
    public string $reason = '';
    public string $refundAmount = '';
    public string $trackingNumber = '';
    public string $accountNumber = '';
    public string $accountHolderName = '';
    public string $bankName = '';
    public string $notes = '';

    // File uploads
    public $attachments = [];

    // Search fields
    public string $orderSearch = '';
    public string $packageSearch = '';
    public string $customerSearch = '';

    public function mount(): void
    {
        $this->returnDate = now()->format('Y-m-d');

        // Check if order_id is passed via URL
        if (request()->has('order_id')) {
            $this->orderId = request()->get('order_id');
            $order = ProductOrder::find($this->orderId);
            if ($order) {
                $this->customerId = $order->customer_id;
                $this->refundAmount = number_format($order->total_amount, 2, '.', '');
            }
        }
    }

    public function getOrders()
    {
        if (empty($this->orderSearch)) {
            return ProductOrder::with('customer')
                ->whereIn('status', ['delivered', 'shipped', 'completed'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        return ProductOrder::with('customer')
            ->where(function ($query) {
                $query->where('order_number', 'like', '%' . $this->orderSearch . '%')
                    ->orWhere('customer_name', 'like', '%' . $this->orderSearch . '%')
                    ->orWhereHas('customer', function ($q) {
                        $q->where('name', 'like', '%' . $this->orderSearch . '%')
                            ->orWhere('email', 'like', '%' . $this->orderSearch . '%');
                    });
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    public function getPackages()
    {
        if (empty($this->packageSearch)) {
            return Package::orderBy('name')->limit(10)->get();
        }

        return Package::where('name', 'like', '%' . $this->packageSearch . '%')
            ->orWhere('slug', 'like', '%' . $this->packageSearch . '%')
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function getCustomers()
    {
        if (empty($this->customerSearch)) {
            return User::orderBy('name')->limit(10)->get();
        }

        return User::where('name', 'like', '%' . $this->customerSearch . '%')
            ->orWhere('email', 'like', '%' . $this->customerSearch . '%')
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function selectOrder(int $orderId): void
    {
        $order = ProductOrder::find($orderId);
        if ($order) {
            $this->orderId = $orderId;
            $this->customerId = $order->customer_id;
            $this->refundAmount = number_format($order->total_amount, 2, '.', '');
            $this->orderSearch = '';
        }
    }

    public function selectPackage(int $packageId): void
    {
        $package = Package::find($packageId);
        if ($package) {
            $this->packageId = $packageId;
            if (empty($this->refundAmount)) {
                $this->refundAmount = number_format($package->price, 2, '.', '');
            }
            $this->packageSearch = '';
        }
    }

    public function selectCustomer(int $customerId): void
    {
        $this->customerId = $customerId;
        $this->customerSearch = '';
    }

    public function clearOrder(): void
    {
        $this->orderId = null;
    }

    public function clearPackage(): void
    {
        $this->packageId = null;
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
    }

    public function removeAttachment(int $index): void
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

    public function getSelectedOrder()
    {
        return $this->orderId ? ProductOrder::with('customer')->find($this->orderId) : null;
    }

    public function getSelectedPackage()
    {
        return $this->packageId ? Package::find($this->packageId) : null;
    }

    public function getSelectedCustomer()
    {
        return $this->customerId ? User::find($this->customerId) : null;
    }

    public function create(): void
    {
        $this->validate([
            'orderId' => 'nullable|exists:product_orders,id',
            'packageId' => 'nullable|exists:packages,id',
            'customerId' => 'nullable|exists:users,id',
            'returnDate' => 'required|date',
            'reason' => 'required|string|max:1000',
            'refundAmount' => 'required|numeric|min:0',
            'trackingNumber' => 'nullable|string|max:100',
            'accountNumber' => 'nullable|string|max:50',
            'accountHolderName' => 'nullable|string|max:100',
            'bankName' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max per file
        ]);

        // At least one of order or package should be selected
        if (!$this->orderId && !$this->packageId) {
            $this->addError('orderId', 'Please select either an order or a package.');
            return;
        }

        // Process attachments
        $attachmentPaths = [];
        if (!empty($this->attachments)) {
            foreach ($this->attachments as $attachment) {
                $path = $attachment->store('refund-attachments', 'public');
                $attachmentPaths[] = [
                    'path' => $path,
                    'name' => $attachment->getClientOriginalName(),
                    'size' => $attachment->getSize(),
                    'type' => $attachment->getMimeType(),
                    'uploaded_at' => now()->toISOString(),
                ];
            }
        }

        $refund = ReturnRefund::create([
            'refund_number' => ReturnRefund::generateRefundNumber(),
            'order_id' => $this->orderId,
            'package_id' => $this->packageId,
            'customer_id' => $this->customerId,
            'return_date' => $this->returnDate,
            'reason' => $this->reason,
            'refund_amount' => $this->refundAmount,
            'tracking_number' => $this->trackingNumber ?: null,
            'account_number' => $this->accountNumber ?: null,
            'account_holder_name' => $this->accountHolderName ?: null,
            'bank_name' => $this->bankName ?: null,
            'notes' => $this->notes ?: null,
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'decision' => 'pending',
            'status' => 'pending_review',
        ]);

        session()->flash('success', 'Return refund request created successfully.');
        $this->redirect(route('admin.customer-service.return-refunds.show', $refund), navigate: true);
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <flux:button variant="ghost" size="sm" :href="route('admin.customer-service.return-refunds.index')" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                    Back to List
                </div>
            </flux:button>
        </div>

        <flux:heading size="xl">New Return/Refund Request</flux:heading>
        <flux:text class="mt-2">Create a new return or refund request for orders and packages</flux:text>
    </div>

    <form wire:submit="create">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Selection -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                        <flux:heading size="lg">Select Order</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Choose the order for this return request</flux:text>
                    </div>
                    <div class="p-6">
                        @if($selectedOrder = $this->getSelectedOrder())
                            <div class="bg-gray-50 dark:bg-zinc-700/50 rounded-lg p-4 flex items-center justify-between">
                                <div>
                                    <flux:text class="font-semibold">{{ $selectedOrder->order_number }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500">
                                        {{ $selectedOrder->getCustomerName() }} - RM {{ number_format($selectedOrder->total_amount, 2) }}
                                    </flux:text>
                                    <flux:text size="sm" class="text-gray-500">
                                        {{ $selectedOrder->created_at->format('M j, Y') }}
                                    </flux:text>
                                </div>
                                <flux:button variant="ghost" size="sm" wire:click="clearOrder">
                                    <flux:icon name="x-mark" class="w-4 h-4" />
                                </flux:button>
                            </div>
                        @else
                            <div class="mb-4">
                                <flux:input wire:model.live.debounce.300ms="orderSearch" placeholder="Search by order number or customer..." />
                            </div>
                            <div class="max-h-60 overflow-y-auto border rounded-lg">
                                @forelse($this->getOrders() as $order)
                                    <button
                                        type="button"
                                        wire:click="selectOrder({{ $order->id }})"
                                        class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-zinc-700/50 border-b dark:border-zinc-700 last:border-b-0 transition-colors"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <flux:text class="font-medium">{{ $order->order_number }}</flux:text>
                                                <flux:text size="sm" class="text-gray-500">{{ $order->getCustomerName() }}</flux:text>
                                            </div>
                                            <div class="text-right">
                                                <flux:text class="font-semibold">RM {{ number_format($order->total_amount, 2) }}</flux:text>
                                                <flux:text size="sm" class="text-gray-500">{{ $order->created_at->format('M j, Y') }}</flux:text>
                                            </div>
                                        </div>
                                    </button>
                                @empty
                                    <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No orders found
                                    </div>
                                @endforelse
                            </div>
                        @endif
                        @error('orderId') <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Package Selection (Optional) -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                        <flux:heading size="lg">Select Package (Optional)</flux:heading>
                        <flux:text size="sm" class="text-gray-500">If this return is for a specific package</flux:text>
                    </div>
                    <div class="p-6">
                        @if($selectedPackage = $this->getSelectedPackage())
                            <div class="bg-purple-50 rounded-lg p-4 flex items-center justify-between">
                                <div>
                                    <flux:text class="font-semibold">{{ $selectedPackage->name }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500">
                                        RM {{ number_format($selectedPackage->price, 2) }}
                                    </flux:text>
                                </div>
                                <flux:button variant="ghost" size="sm" wire:click="clearPackage">
                                    <flux:icon name="x-mark" class="w-4 h-4" />
                                </flux:button>
                            </div>
                        @else
                            <div class="mb-4">
                                <flux:input wire:model.live.debounce.300ms="packageSearch" placeholder="Search packages..." />
                            </div>
                            <div class="max-h-48 overflow-y-auto border rounded-lg">
                                @forelse($this->getPackages() as $package)
                                    <button
                                        type="button"
                                        wire:click="selectPackage({{ $package->id }})"
                                        class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-zinc-700/50 border-b dark:border-zinc-700 last:border-b-0 transition-colors"
                                    >
                                        <div class="flex items-center justify-between">
                                            <flux:text class="font-medium">{{ $package->name }}</flux:text>
                                            <flux:text class="font-semibold">RM {{ number_format($package->price, 2) }}</flux:text>
                                        </div>
                                    </button>
                                @empty
                                    <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No packages found
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Return Details -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                        <flux:heading size="lg">Return Details</flux:heading>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <flux:label for="returnDate">Return Date *</flux:label>
                                <flux:input type="date" wire:model="returnDate" id="returnDate" required />
                                @error('returnDate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <flux:label for="refundAmount">Refund Amount (RM) *</flux:label>
                                <flux:input type="number" step="0.01" wire:model="refundAmount" id="refundAmount" placeholder="0.00" required />
                                @error('refundAmount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <flux:label for="reason">Reason for Return/Refund *</flux:label>
                                <flux:textarea wire:model="reason" id="reason" rows="4" placeholder="Please describe the reason for this return or refund request..." required />
                                @error('reason') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tracking & Bank Details -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                        <flux:heading size="lg">Tracking & Bank Details</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Optional - can be added later</flux:text>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <flux:label for="trackingNumber">Return Tracking Number</flux:label>
                                <flux:input wire:model="trackingNumber" id="trackingNumber" placeholder="Enter tracking number" />
                            </div>
                            <div>
                                <flux:label for="bankName">Bank Name</flux:label>
                                <flux:input wire:model="bankName" id="bankName" placeholder="e.g., Maybank, CIMB" />
                            </div>
                            <div>
                                <flux:label for="accountNumber">Bank Account Number</flux:label>
                                <flux:input wire:model="accountNumber" id="accountNumber" placeholder="Enter account number" />
                            </div>
                            <div>
                                <flux:label for="accountHolderName">Account Holder Name</flux:label>
                                <flux:input wire:model="accountHolderName" id="accountHolderName" placeholder="Enter account holder name" />
                            </div>
                            <div class="md:col-span-2">
                                <flux:label for="notes">Internal Notes</flux:label>
                                <flux:textarea wire:model="notes" id="notes" rows="3" placeholder="Add any internal notes..." />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachments / Evidence -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                        <flux:heading size="lg">Attachments / Evidence</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Upload proof of purchase, payment receipts, or other relevant documents (JPG, PNG, PDF - max 5MB each)</flux:text>
                    </div>
                    <div class="p-6">
                        <!-- File Upload Area -->
                        <div class="mb-4">
                            <label for="attachments" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:hover:bg-zinc-700 dark:bg-zinc-800 hover:bg-gray-100 dark:border-zinc-600 dark:hover:border-zinc-500 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <flux:icon name="cloud-arrow-up" class="w-8 h-8 mb-2 text-gray-400" />
                                    <p class="mb-1 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">JPG, PNG or PDF (max 5MB per file)</p>
                                </div>
                                <input id="attachments" type="file" class="hidden" wire:model="attachments" multiple accept=".jpg,.jpeg,.png,.pdf" />
                            </label>
                            @error('attachments.*') <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Upload Progress -->
                        <div wire:loading wire:target="attachments" class="mb-4">
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Uploading files...
                            </div>
                        </div>

                        <!-- Uploaded Files Preview -->
                        @if(count($attachments) > 0)
                            <div class="space-y-2">
                                <flux:text size="sm" class="font-medium text-gray-700 dark:text-gray-300">Files to upload ({{ count($attachments) }})</flux:text>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach($attachments as $index => $attachment)
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg border border-gray-200 dark:border-zinc-600">
                                            @if(str_starts_with($attachment->getMimeType(), 'image/'))
                                                <div class="w-12 h-12 rounded overflow-hidden flex-shrink-0 bg-gray-200">
                                                    <img src="{{ $attachment->temporaryUrl() }}" alt="Preview" class="w-full h-full object-cover" />
                                                </div>
                                            @else
                                                <div class="w-12 h-12 rounded flex items-center justify-center flex-shrink-0 bg-red-100 dark:bg-red-900/30">
                                                    <flux:icon name="document" class="w-6 h-6 text-red-600" />
                                                </div>
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <flux:text size="sm" class="font-medium truncate">{{ $attachment->getClientOriginalName() }}</flux:text>
                                                <flux:text size="xs" class="text-gray-500">{{ number_format($attachment->getSize() / 1024, 1) }} KB</flux:text>
                                            </div>
                                            <button type="button" wire:click="removeAttachment({{ $index }})" class="text-gray-400 hover:text-red-500 transition-colors">
                                                <flux:icon name="x-mark" class="w-5 h-5" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Customer Selection -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                        <flux:heading size="lg">Customer</flux:heading>
                    </div>
                    <div class="p-6">
                        @if($selectedCustomer = $this->getSelectedCustomer())
                            <div class="bg-gray-50 dark:bg-zinc-700/50 rounded-lg p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                        <flux:icon name="user" class="w-5 h-5 text-gray-500" />
                                    </div>
                                    <div>
                                        <flux:text class="font-semibold">{{ $selectedCustomer->name }}</flux:text>
                                        <flux:text size="sm" class="text-gray-500">{{ $selectedCustomer->email }}</flux:text>
                                    </div>
                                </div>
                                <flux:button variant="ghost" size="sm" wire:click="clearCustomer">
                                    <flux:icon name="x-mark" class="w-4 h-4" />
                                </flux:button>
                            </div>
                        @else
                            <div class="mb-4">
                                <flux:input wire:model.live.debounce.300ms="customerSearch" placeholder="Search customers..." />
                            </div>
                            <div class="max-h-48 overflow-y-auto border rounded-lg">
                                @forelse($this->getCustomers() as $customer)
                                    <button
                                        type="button"
                                        wire:click="selectCustomer({{ $customer->id }})"
                                        class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-zinc-700/50 border-b dark:border-zinc-700 last:border-b-0 transition-colors"
                                    >
                                        <flux:text class="font-medium">{{ $customer->name }}</flux:text>
                                        <flux:text size="sm" class="text-gray-500">{{ $customer->email }}</flux:text>
                                    </button>
                                @empty
                                    <div class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                        No customers found
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                        <flux:heading size="lg">Summary</flux:heading>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:text class="text-gray-600">Order</flux:text>
                            <flux:text class="font-medium">
                                @if($this->getSelectedOrder())
                                    {{ $this->getSelectedOrder()->order_number }}
                                @else
                                    <span class="text-gray-400">Not selected</span>
                                @endif
                            </flux:text>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text class="text-gray-600">Package</flux:text>
                            <flux:text class="font-medium">
                                @if($this->getSelectedPackage())
                                    {{ $this->getSelectedPackage()->name }}
                                @else
                                    <span class="text-gray-400">None</span>
                                @endif
                            </flux:text>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text class="text-gray-600">Customer</flux:text>
                            <flux:text class="font-medium">
                                @if($this->getSelectedCustomer())
                                    {{ $this->getSelectedCustomer()->name }}
                                @else
                                    <span class="text-gray-400">Not selected</span>
                                @endif
                            </flux:text>
                        </div>
                        <div class="border-t pt-4">
                            <div class="flex items-center justify-between">
                                <flux:text class="text-gray-600">Refund Amount</flux:text>
                                <flux:text class="text-xl font-bold text-green-600">
                                    RM {{ $refundAmount ? number_format((float)$refundAmount, 2) : '0.00' }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <flux:button type="submit" variant="primary" class="w-full mb-3">
                        <div class="flex items-center justify-center">
                            <flux:icon name="plus" class="w-4 h-4 mr-2" />
                            Create Return Request
                        </div>
                    </flux:button>
                    <flux:button variant="ghost" class="w-full" :href="route('admin.customer-service.return-refunds.index')" wire:navigate>
                        Cancel
                    </flux:button>
                </div>
            </div>
        </div>
    </form>
</div>
