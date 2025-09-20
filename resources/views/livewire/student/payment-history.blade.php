<?php
use App\Models\Payment;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';
    public string $dateRange = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function mount()
    {
        // Ensure user is a student
        if (!auth()->user()->isStudent()) {
            abort(403, 'Access denied');
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedTypeFilter()
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
        // Get payments for the current user
        $payments = $this->getPayments();

        // Calculate statistics
        $stats = $this->calculateStats();

        return [
            'payments' => $payments,
            ...$stats,
        ];
    }

    private function calculateStats(): array
    {
        $baseQuery = Payment::where('user_id', auth()->id());
        
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

        // Total payments
        $totalPayments = (clone $baseQuery)->count();
        
        // Successful payments
        $successfulPayments = (clone $baseQuery)->successful()->count();
        
        // Failed payments
        $failedPayments = (clone $baseQuery)->failed()->count();
        
        // Total amount paid
        $totalAmountPaid = (clone $baseQuery)->successful()->sum('amount');
        
        // Pending payments
        $pendingPayments = (clone $baseQuery)->pending()->count();

        return [
            'totalPayments' => $totalPayments,
            'successfulPayments' => $successfulPayments,
            'failedPayments' => $failedPayments,
            'pendingPayments' => $pendingPayments,
            'totalAmountPaid' => $totalAmountPaid,
        ];
    }

    private function getPayments()
    {
        $query = Payment::with(['invoice.course'])
            ->where('user_id', auth()->id())
            ->when($this->search, function($q) {
                $q->whereHas('invoice', function($invoiceQuery) {
                    $invoiceQuery->where('invoice_number', 'like', '%' . $this->search . '%')
                                ->orWhereHas('course', function($courseQuery) {
                                    $courseQuery->where('name', 'like', '%' . $this->search . '%');
                                });
                })->orWhere('stripe_payment_intent_id', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->typeFilter, function($q) {
                $q->where('type', $this->typeFilter);
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

        return $query->paginate(15);
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
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Payment History</flux:heading>
            <flux:text class="mt-2">View all your payment transactions and receipts</flux:text>
        </div>
        <flux:button variant="outline" icon="document" href="{{ route('student.invoices') }}" wire:navigate>
            My Invoices
        </flux:button>
    </div>

    <!-- Statistics Cards -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Total Paid</flux:heading>
                    <flux:heading size="xl" class="text-emerald-600">RM {{ number_format($totalAmountPaid, 2) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">{{ $successfulPayments }} successful</flux:text>
                </div>
                <flux:icon icon="currency-dollar" class="w-8 h-8 text-emerald-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Total Payments</flux:heading>
                    <flux:heading size="xl" class="text-blue-600">{{ $totalPayments }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">All transactions</flux:text>
                </div>
                <flux:icon icon="credit-card" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Pending</flux:heading>
                    <flux:heading size="xl" class="text-amber-600">{{ $pendingPayments }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Awaiting processing</flux:text>
                </div>
                <flux:icon icon="clock" class="w-8 h-8 text-amber-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Failed</flux:heading>
                    <flux:heading size="xl" class="text-red-600">{{ $failedPayments }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Unsuccessful attempts</flux:text>
                </div>
                <flux:icon icon="exclamation-triangle" class="w-8 h-8 text-red-500" />
            </div>
        </flux:card>
    </div>

    <!-- Payments Table -->
    <flux:card>
        <flux:header>
            <flux:heading size="lg">Payment Transactions</flux:heading>
            
            <div class="flex items-center space-x-3">
                <!-- Search -->
                <flux:input 
                    wire:model.live="search" 
                    placeholder="Search payments..."
                    class="w-64"
                />
                
                <!-- Status Filter -->
                <flux:select wire:model.live="statusFilter" placeholder="All Statuses" class="w-40">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    @foreach(Payment::getStatuses() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <!-- Type Filter -->
                <flux:select wire:model.live="typeFilter" placeholder="All Types" class="w-40">
                    <flux:select.option value="">All Types</flux:select.option>
                    @foreach(Payment::getTypes() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <!-- Date Range -->
                <flux:input type="month" wire:model.live="dateRange" class="w-36" />
            </div>
        </flux:header>

        @if($payments->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4">
                                <button wire:click="sortBy('created_at')" class="flex items-center space-x-1 hover:text-blue-600">
                                    <span>Date</span>
                                    @if($sortBy === 'created_at')
                                        <flux:icon icon="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                    @endif
                                </button>
                            </th>
                            <th class="text-left py-3 px-4">Invoice</th>
                            <th class="text-right py-3 px-4">
                                <button wire:click="sortBy('amount')" class="flex items-center space-x-1 hover:text-blue-600 ml-auto">
                                    <span>Amount</span>
                                    @if($sortBy === 'amount')
                                        <flux:icon icon="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                    @endif
                                </button>
                            </th>
                            <th class="text-center py-3 px-4">Type</th>
                            <th class="text-center py-3 px-4">Status</th>
                            <th class="text-right py-3 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            <tr class="border-b border-gray-100  hover:bg-gray-50 :bg-gray-800/50">
                                <td class="py-3 px-4">
                                    <div class="font-medium">{{ $payment->created_at->format('M d, Y') }}</div>
                                    <div class="text-sm text-gray-600">{{ $payment->created_at->format('H:i') }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <flux:link :href="route('student.invoices.show', $payment->invoice)" class="font-medium hover:text-blue-600" wire:navigate>
                                        {{ $payment->invoice->invoice_number }}
                                    </flux:link>
                                    <div class="text-sm text-gray-600">{{ $payment->invoice->course->name }}</div>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="font-medium">{{ $payment->formatted_amount }}</div>
                                    @if($payment->stripe_fee > 0)
                                        <div class="text-sm text-gray-600">Fee: RM {{ number_format($payment->stripe_fee, 2) }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <flux:badge color="{{ $payment->isStripePayment() ? 'blue' : 'green' }}" size="sm">
                                        {{ $payment->type_label }}
                                    </flux:badge>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <flux:badge :color="$this->getStatusBadgeColor($payment->status)">
                                        {{ $payment->status_label }}
                                    </flux:badge>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <flux:button 
                                        variant="ghost" 
                                        size="sm" 
                                        icon="eye"
                                        :href="route('student.payments.show', $payment)" 
                                        wire:navigate
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $payments->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <flux:icon icon="credit-card" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <flux:heading size="md" class="text-gray-600  mb-2">No payment history</flux:heading>
                <flux:text class="text-gray-600">
                    @if($search || $statusFilter || $typeFilter)
                        No payments match your current filters.
                        <button wire:click="$set('search', '')" wire:click="$set('statusFilter', '')" wire:click="$set('typeFilter', '')" class="text-blue-600 hover:underline ml-1">Clear filters</button>
                    @else
                        You haven't made any payments yet.
                        <flux:link :href="route('student.invoices')" class="text-blue-600 hover:underline ml-1">View your invoices</flux:link>
                    @endif
                </flux:text>
            </div>
        @endif
    </flux:card>
</div>