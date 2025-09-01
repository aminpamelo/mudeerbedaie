<?php
use App\Models\Payment;
use App\Mail\PaymentConfirmation;
use App\Mail\PaymentFailed;
use Illuminate\Support\Facades\Mail;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $dateRange = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function mount()
    {
        // Ensure user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        // Default to current month
        $this->dateRange = now()->format('Y-m');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedDateRange()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function with(): array
    {
        // Calculate statistics for bank transfers
        $stats = $this->calculateStats();
        
        // Get bank transfer payments with filters
        $bankTransfers = $this->getBankTransfers();

        return [
            'bankTransfers' => $bankTransfers,
            ...$stats,
        ];
    }

    private function calculateStats(): array
    {
        $baseQuery = Payment::bankTransfers();
        
        // Apply date filter to stats if specified
        if ($this->dateRange) {
            if (strlen($this->dateRange) === 7) { // YYYY-MM format
                $startDate = Carbon::createFromFormat('Y-m', $this->dateRange)->startOfMonth();
                $endDate = Carbon::createFromFormat('Y-m', $this->dateRange)->endOfMonth();
            } else {
                $startDate = Carbon::parse($this->dateRange)->startOfDay();
                $endDate = Carbon::parse($this->dateRange)->endOfDay();
            }
            
            $baseQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Total bank transfers
        $totalBankTransfers = (clone $baseQuery)->count();
        
        // Pending bank transfers (awaiting approval)
        $pendingBankTransfers = (clone $baseQuery)->pending()->count();
        
        // Approved bank transfers
        $approvedBankTransfers = (clone $baseQuery)->successful()->count();
        
        // Rejected bank transfers
        $rejectedBankTransfers = (clone $baseQuery)->failed()->count();
        
        // Total amount from approved transfers
        $totalAmount = (clone $baseQuery)->successful()->sum('amount');

        return [
            'totalBankTransfers' => $totalBankTransfers,
            'pendingBankTransfers' => $pendingBankTransfers,
            'approvedBankTransfers' => $approvedBankTransfers,
            'rejectedBankTransfers' => $rejectedBankTransfers,
            'totalAmount' => $totalAmount,
        ];
    }

    private function getBankTransfers()
    {
        $query = Payment::with(['user', 'invoice.course'])
            ->bankTransfers()
            ->when($this->search, function($q) {
                $q->whereHas('user', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->search . '%')
                             ->orWhere('email', 'like', '%' . $this->search . '%');
                })->orWhereHas('invoice', function($invoiceQuery) {
                    $invoiceQuery->where('invoice_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->dateRange, function($q) {
                if (strlen($this->dateRange) === 7) { // YYYY-MM format
                    $startDate = Carbon::createFromFormat('Y-m', $this->dateRange)->startOfMonth();
                    $endDate = Carbon::createFromFormat('Y-m', $this->dateRange)->endOfMonth();
                } else {
                    $startDate = Carbon::parse($this->dateRange)->startOfDay();
                    $endDate = Carbon::parse($this->dateRange)->endOfDay();
                }
                return $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate(20);
    }

    public function getStatusBadgeColor($status): string
    {
        return match($status) {
            Payment::STATUS_SUCCEEDED => 'emerald',
            Payment::STATUS_FAILED, Payment::STATUS_CANCELLED => 'red',
            Payment::STATUS_PENDING => 'amber',
            default => 'gray'
        };
    }

    public function approveTransfer($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        
        if (!$payment->isPending()) {
            session()->flash('error', 'Only pending payments can be approved.');
            return;
        }

        try {
            // Update payment status
            $payment->update([
                'status' => Payment::STATUS_SUCCEEDED,
                'paid_at' => now(),
                'notes' => $payment->notes . "\n\nApproved by: " . auth()->user()->name . " on " . now()->format('Y-m-d H:i:s')
            ]);

            // Mark invoice as paid
            if ($payment->invoice->isFullyPaid()) {
                $payment->invoice->markAsPaid();
            }

            // Send confirmation email
            try {
                Mail::to($payment->user->email)->send(new PaymentConfirmation($payment));
            } catch (\Exception $e) {
                // Log email error but don't fail the approval
                \Log::warning('Failed to send payment confirmation email', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }

            session()->flash('success', 'Bank transfer approved successfully and student has been notified.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to approve transfer: ' . $e->getMessage());
        }
    }

    public function rejectTransfer($paymentId, $reason = null)
    {
        $payment = Payment::findOrFail($paymentId);
        
        if (!$payment->isPending()) {
            session()->flash('error', 'Only pending payments can be rejected.');
            return;
        }

        try {
            $rejectionReason = $reason ?: 'Bank transfer verification failed';
            
            // Update payment status
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => ['reason' => $rejectionReason],
                'notes' => $payment->notes . "\n\nRejected by: " . auth()->user()->name . " on " . now()->format('Y-m-d H:i:s') . "\nReason: " . $rejectionReason
            ]);

            // Send rejection email
            try {
                Mail::to($payment->user->email)->send(new PaymentFailed($payment));
            } catch (\Exception $e) {
                // Log email error but don't fail the rejection
                \Log::warning('Failed to send payment rejection email', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }

            session()->flash('success', 'Bank transfer rejected and student has been notified.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to reject transfer: ' . $e->getMessage());
        }
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Bank Transfers</flux:heading>
            <flux:text class="mt-2">Manage bank transfer submissions and approvals</flux:text>
        </div>
        <flux:button variant="outline" icon="arrow-left" href="{{ route('admin.payments') }}" wire:navigate>
            All Payments
        </flux:button>
    </div>

    <!-- Success/Error Messages -->
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

    <!-- Statistics Cards -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Pending Review</flux:heading>
                    <flux:heading size="xl" class="text-amber-600">{{ $pendingBankTransfers }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Awaiting approval</flux:text>
                </div>
                <flux:icon icon="clock" class="w-8 h-8 text-amber-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Approved</flux:heading>
                    <flux:heading size="xl" class="text-emerald-600">{{ $approvedBankTransfers }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Successfully processed</flux:text>
                </div>
                <flux:icon icon="check-circle" class="w-8 h-8 text-emerald-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Rejected</flux:heading>
                    <flux:heading size="xl" class="text-red-600">{{ $rejectedBankTransfers }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Declined transfers</flux:text>
                </div>
                <flux:icon icon="x-circle" class="w-8 h-8 text-red-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Total Value</flux:heading>
                    <flux:heading size="xl" class="text-blue-600">RM {{ number_format($totalAmount, 2) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Approved transfers</flux:text>
                </div>
                <flux:icon icon="currency-dollar" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>
    </div>

    <!-- Bank Transfers Table -->
    <flux:card>
        <flux:header>
            <flux:heading size="lg">Bank Transfer Submissions</flux:heading>
            
            <div class="flex items-center space-x-3">
                <!-- Search -->
                <flux:input 
                    wire:model.live="search" 
                    placeholder="Search transfers..."
                    class="w-64"
                />
                
                <!-- Status Filter -->
                <flux:select wire:model.live="statusFilter" placeholder="All Statuses" class="w-40">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    <flux:select.option value="{{ Payment::STATUS_PENDING }}">Pending</flux:select.option>
                    <flux:select.option value="{{ Payment::STATUS_SUCCEEDED }}">Approved</flux:select.option>
                    <flux:select.option value="{{ Payment::STATUS_FAILED }}">Rejected</flux:select.option>
                </flux:select>

                <!-- Date Range -->
                <flux:input type="month" wire:model.live="dateRange" class="w-36" />
            </div>
        </flux:header>

        @if($bankTransfers->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4">
                                <button wire:click="sortBy('created_at')" class="flex items-center space-x-1 hover:text-blue-600">
                                    <span>Submitted</span>
                                    @if($sortBy === 'created_at')
                                        <flux:icon icon="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                    @endif
                                </button>
                            </th>
                            <th class="text-left py-3 px-4">Student</th>
                            <th class="text-left py-3 px-4">Invoice</th>
                            <th class="text-right py-3 px-4">
                                <button wire:click="sortBy('amount')" class="flex items-center space-x-1 hover:text-blue-600 ml-auto">
                                    <span>Amount</span>
                                    @if($sortBy === 'amount')
                                        <flux:icon icon="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                    @endif
                                </button>
                            </th>
                            <th class="text-center py-3 px-4">Status</th>
                            <th class="text-center py-3 px-4">Reference</th>
                            <th class="text-right py-3 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bankTransfers as $transfer)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="py-3 px-4">
                                    <div class="font-medium">{{ $transfer->created_at->format('M d, Y') }}</div>
                                    <div class="text-sm text-gray-600">{{ $transfer->created_at->format('H:i') }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium">{{ $transfer->user->name }}</div>
                                    <div class="text-sm text-gray-600">{{ $transfer->user->email }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <flux:link :href="route('invoices.show', $transfer->invoice)" class="font-medium hover:text-blue-600" wire:navigate>
                                        {{ $transfer->invoice->invoice_number }}
                                    </flux:link>
                                    <div class="text-sm text-gray-600">{{ $transfer->invoice->course->name }}</div>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="font-medium">{{ $transfer->formatted_amount }}</div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <flux:badge :color="$this->getStatusBadgeColor($transfer->status)">
                                        {{ $transfer->status_label }}
                                    </flux:badge>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @if(isset($transfer->stripe_metadata['transaction_reference']))
                                        <flux:text class="font-mono text-sm">{{ $transfer->stripe_metadata['transaction_reference'] }}</flux:text>
                                    @else
                                        <flux:text class="text-gray-400 text-sm">-</flux:text>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        @if($transfer->isPending())
                                            <!-- Quick approve/reject buttons for pending transfers -->
                                            <flux:button 
                                                variant="ghost" 
                                                size="sm" 
                                                color="emerald"
                                                icon="check"
                                                title="Quick Approve"
                                                wire:click="approveTransfer({{ $transfer->id }})"
                                                wire:confirm="Are you sure you want to approve this bank transfer? This action cannot be undone."
                                            />
                                            <flux:button 
                                                variant="ghost" 
                                                size="sm" 
                                                color="red"
                                                icon="x-mark"
                                                title="Quick Reject"
                                                wire:click="rejectTransfer({{ $transfer->id }})"
                                                wire:confirm="Are you sure you want to reject this bank transfer? The student will be notified."
                                            />
                                        @endif
                                        
                                        <flux:button 
                                            variant="ghost" 
                                            size="sm" 
                                            icon="eye"
                                            :href="route('admin.payments.show', $transfer)" 
                                            wire:navigate
                                            title="View Details"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $bankTransfers->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <flux:icon icon="building-library" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <flux:heading size="md" class="text-gray-600 dark:text-gray-400 mb-2">No bank transfers found</flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400">
                    @if($search || $statusFilter)
                        No bank transfers match your current filters.
                        <button 
                            wire:click="$set('search', '')" 
                            wire:click="$set('statusFilter', '')" 
                            class="text-blue-600 hover:underline ml-1"
                        >
                            Clear filters
                        </button>
                    @else
                        No bank transfer submissions have been received yet.
                    @endif
                </flux:text>
            </div>
        @endif
    </flux:card>

    <!-- Info Card -->
    <flux:card class="mt-6">
        <flux:header>
            <flux:heading size="lg">Bank Transfer Guidelines</flux:heading>
        </flux:header>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <flux:heading size="sm" class="mb-3">Review Process</flux:heading>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-center">
                        <flux:icon icon="check" class="w-4 h-4 text-emerald-500 mr-2" />
                        Students submit bank transfer details with proof
                    </li>
                    <li class="flex items-center">
                        <flux:icon icon="eye" class="w-4 h-4 text-blue-500 mr-2" />
                        Admin reviews submission and verifies payment
                    </li>
                    <li class="flex items-center">
                        <flux:icon icon="check-circle" class="w-4 h-4 text-emerald-500 mr-2" />
                        Payment is approved or rejected based on verification
                    </li>
                </ul>
            </div>
            
            <div>
                <flux:heading size="sm" class="mb-3">Verification Tips</flux:heading>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-center">
                        <flux:icon icon="currency-dollar" class="w-4 h-4 text-blue-500 mr-2" />
                        Verify transfer amount matches invoice amount
                    </li>
                    <li class="flex items-center">
                        <flux:icon icon="calendar" class="w-4 h-4 text-purple-500 mr-2" />
                        Check transfer date is recent and logical
                    </li>
                    <li class="flex items-center">
                        <flux:icon icon="document-text" class="w-4 h-4 text-amber-500 mr-2" />
                        Review proof of payment document carefully
                    </li>
                </ul>
            </div>
        </div>
    </flux:card>
</div>