<?php
use App\Models\Order;
use App\Models\Enrollment;
use App\Models\WebhookEvent;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

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
        // Calculate statistics
        $stats = $this->calculateStats();
        
        // Get orders with filters
        $orders = $this->getOrders();

        return [
            'orders' => $orders,
            ...$stats,
        ];
    }

    private function calculateStats(): array
    {
        $baseQuery = Order::query();
        
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

        // Total revenue (paid orders)
        $totalRevenue = (clone $baseQuery)->paid()->sum('amount');
        
        // Total orders count
        $totalOrders = (clone $baseQuery)->count();
        
        // Successful orders
        $successfulOrders = (clone $baseQuery)->paid()->count();
        
        // Failed orders
        $failedOrders = (clone $baseQuery)->failed()->count();
        
        // Pending orders
        $pendingOrders = (clone $baseQuery)->pending()->count();
        
        // Stripe fees total
        $stripeFees = (clone $baseQuery)->paid()->sum('stripe_fee');
        
        // Net revenue (after fees)
        $netRevenue = $totalRevenue - $stripeFees;

        // Average order amount
        $averageOrder = $successfulOrders > 0 ? $totalRevenue / $successfulOrders : 0;

        // Success rate
        $successRate = $totalOrders > 0 ? ($successfulOrders / $totalOrders) * 100 : 0;

        // Recent activity (last 24 hours)
        $recentActivity = Order::with(['student.user', 'course', 'enrollment'])
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Billing reason breakdown
        $billingReasonStats = Order::selectRaw('billing_reason, status, COUNT(*) as count, SUM(amount) as total_amount')
            ->when($this->dateRange, function($query) {
                if (strlen($this->dateRange) === 7) {
                    $startDate = Carbon::createFromFormat('Y-m', $this->dateRange)->startOfMonth();
                    $endDate = Carbon::createFromFormat('Y-m', $this->dateRange)->endOfMonth();
                } else {
                    $startDate = Carbon::parse($this->dateRange)->startOfDay();
                    $endDate = Carbon::parse($this->dateRange)->endOfDay();
                }
                return $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('billing_reason', 'status')
            ->get()
            ->groupBy('billing_reason');

        // Webhook event stats
        $webhookStats = $this->getWebhookStats();

        return [
            'totalRevenue' => $totalRevenue,
            'netRevenue' => $netRevenue,
            'stripeFees' => $stripeFees,
            'totalOrders' => $totalOrders,
            'successfulOrders' => $successfulOrders,
            'failedOrders' => $failedOrders,
            'pendingOrders' => $pendingOrders,
            'averageOrder' => $averageOrder,
            'successRate' => $successRate,
            'recentActivity' => $recentActivity,
            'billingReasonStats' => $billingReasonStats,
            'webhookStats' => $webhookStats,
        ];
    }

    private function getOrders()
    {
        $query = Order::with(['student.user', 'course', 'enrollment'])
            ->when($this->search, function($q) {
                $q->whereHas('student.user', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->search . '%')
                             ->orWhere('email', 'like', '%' . $this->search . '%');
                })->orWhere('order_number', 'like', '%' . $this->search . '%')
                  ->orWhere('stripe_invoice_id', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->typeFilter, function($q) {
                $q->where('billing_reason', $this->typeFilter);
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
            Order::STATUS_PAID => 'emerald',
            Order::STATUS_FAILED => 'red',
            Order::STATUS_PENDING => 'amber',
            Order::STATUS_REFUNDED, Order::STATUS_VOID => 'purple',
            default => 'gray'
        };
    }

    public function exportOrders()
    {
        $orders = $this->getOrdersForExport();
        
        $csvData = [];
        $csvData[] = [
            'Date', 'Order Number', 'Student Name', 'Student Email', 'Course', 
            'Amount', 'Stripe Fee', 'Net Amount', 'Billing Reason', 'Status', 
            'Stripe Invoice ID', 'Created At'
        ];

        foreach ($orders as $order) {
            $csvData[] = [
                $order->created_at->format('Y-m-d'),
                $order->order_number,
                $order->student->user->name,
                $order->student->user->email,
                $order->course->name,
                $order->amount,
                $order->stripe_fee ?? 0,
                $order->net_amount ?? ($order->amount - ($order->stripe_fee ?? 0)),
                $order->billing_reason_label,
                $order->status_label,
                $order->stripe_invoice_id,
                $order->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $filename = 'orders_export_' . now()->format('Y_m_d_His') . '.csv';
        
        $handle = fopen('php://memory', 'r+');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return Response::streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function getOrdersForExport()
    {
        return Order::with(['student.user', 'course', 'enrollment'])
            ->when($this->search, function($q) {
                $q->whereHas('student.user', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->search . '%')
                             ->orWhere('email', 'like', '%' . $this->search . '%');
                })->orWhere('order_number', 'like', '%' . $this->search . '%')
                  ->orWhere('stripe_invoice_id', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->typeFilter, function($q) {
                $q->where('billing_reason', $this->typeFilter);
            })
            ->when($this->dateRange, function($q) {
                if (strlen($this->dateRange) === 7) {
                    $startDate = Carbon::createFromFormat('Y-m', $this->dateRange)->startOfMonth();
                    $endDate = Carbon::createFromFormat('Y-m', $this->dateRange)->endOfMonth();
                } else {
                    $startDate = Carbon::parse($this->dateRange)->startOfDay();
                    $endDate = Carbon::parse($this->dateRange)->endOfDay();
                }
                return $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->get();
    }


    private function getWebhookStats(): array
    {
        $dateFilter = null;
        if ($this->dateRange) {
            if (strlen($this->dateRange) === 7) {
                $startDate = Carbon::createFromFormat('Y-m', $this->dateRange)->startOfMonth();
                $endDate = Carbon::createFromFormat('Y-m', $this->dateRange)->endOfMonth();
            } else {
                $startDate = Carbon::parse($this->dateRange)->startOfDay();
                $endDate = Carbon::parse($this->dateRange)->endOfDay();
            }
            $dateFilter = [$startDate, $endDate];
        }

        $baseQuery = WebhookEvent::query();
        if ($dateFilter) {
            $baseQuery->whereBetween('created_at', $dateFilter);
        }

        $totalWebhooks = (clone $baseQuery)->count();
        $processedWebhooks = (clone $baseQuery)->processed()->count();
        $failedWebhooks = (clone $baseQuery)->failed()->count();
        $pendingWebhooks = (clone $baseQuery)->pending()->where('error_message', null)->count();

        return [
            'totalWebhooks' => $totalWebhooks,
            'processedWebhooks' => $processedWebhooks,
            'failedWebhooks' => $failedWebhooks,
            'pendingWebhooks' => $pendingWebhooks,
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Subscription Dashboard</flux:heading>
            <flux:text class="mt-2">Monitor and manage subscription orders and payments</flux:text>
        </div>
        <div class="flex items-center space-x-3">
            <flux:button variant="outline" href="{{ route('orders.index') }}" wire:navigate>
                <flux:icon icon="clipboard-document-list" class="w-4 h-4" />
                View All Orders
            </flux:button>
            <flux:button variant="outline" wire:click="exportOrders">
                <flux:icon icon="arrow-down-tray" class="w-4 h-4" />
                Export CSV
            </flux:button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Total Revenue</flux:heading>
                    <flux:heading size="xl" class="text-emerald-600">RM {{ number_format($totalRevenue, 2) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">{{ $successfulOrders }} successful orders</flux:text>
                </div>
                <flux:icon icon="currency-dollar" class="w-8 h-8 text-emerald-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Success Rate</flux:heading>
                    <flux:heading size="xl" class="text-blue-600">{{ number_format($successRate, 1) }}%</flux:heading>
                    <flux:text size="sm" class="text-gray-600">{{ $totalOrders }} total orders</flux:text>
                </div>
                <flux:icon icon="chart-bar" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Pending</flux:heading>
                    <flux:heading size="xl" class="text-amber-600">{{ $pendingOrders }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Awaiting payment</flux:text>
                </div>
                <flux:icon icon="clock" class="w-8 h-8 text-amber-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Failed</flux:heading>
                    <flux:heading size="xl" class="text-red-600">{{ $failedOrders }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Payment failed</flux:text>
                </div>
                <flux:icon icon="exclamation-triangle" class="w-8 h-8 text-red-500" />
            </div>
        </flux:card>
    </div>


    <!-- Revenue Breakdown -->
    <div class="grid gap-6 lg:grid-cols-2 mb-6">
        <flux:card>
            <flux:header>
                <flux:heading size="lg">Revenue Breakdown</flux:heading>
            </flux:header>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <flux:text class="text-gray-600">Gross Revenue</flux:text>
                    <flux:text class="font-medium">RM {{ number_format($totalRevenue, 2) }}</flux:text>
                </div>
                <div class="flex justify-between items-center">
                    <flux:text class="text-gray-600">Stripe Fees</flux:text>
                    <flux:text class="font-medium text-red-600">-RM {{ number_format($stripeFees, 2) }}</flux:text>
                </div>
                <div class="border-t pt-4">
                    <div class="flex justify-between items-center">
                        <flux:text class="font-medium">Net Revenue</flux:text>
                        <flux:text class="font-bold text-emerald-600">RM {{ number_format($netRevenue, 2) }}</flux:text>
                    </div>
                </div>
                <div class="text-center pt-4 border-t">
                    <flux:text class="text-gray-600">Average Order</flux:text>
                    <flux:text class="font-medium">RM {{ number_format($averageOrder, 2) }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <flux:header>
                <flux:heading size="lg">Billing Reasons</flux:heading>
            </flux:header>
            
            <div class="space-y-4">
                @foreach($billingReasonStats as $reason => $stats)
                    <div class="border-l-4 border-blue-500 pl-4">
                        <flux:text class="font-medium">{{ ucfirst(str_replace('_', ' ', $reason)) }}</flux:text>
                        <div class="grid grid-cols-2 gap-4 mt-2 text-sm">
                            @php
                                $successful = $stats->where('status', Order::STATUS_PAID)->first();
                                $failed = $stats->where('status', Order::STATUS_FAILED)->first();
                                $pending = $stats->where('status', Order::STATUS_PENDING)->first();
                            @endphp
                            
                            @if($successful)
                                <div>
                                    <span class="text-emerald-600">✓ {{ $successful->count }} paid</span>
                                    <div class="text-gray-600">RM {{ number_format($successful->total_amount, 2) }}</div>
                                </div>
                            @endif
                            
                            @if($failed)
                                <div>
                                    <span class="text-red-600">✗ {{ $failed->count }} failed</span>
                                </div>
                            @endif
                            
                            @if($pending)
                                <div>
                                    <span class="text-amber-600">⏳ {{ $pending->count }} pending</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>

    <!-- Webhook Monitoring -->
    @if($webhookStats['totalWebhooks'] > 0)
        <flux:card class="mb-6">
            <flux:header>
                <flux:heading size="lg">Webhook Monitoring</flux:heading>
            </flux:header>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-blue-50 /20 rounded-lg">
                    <flux:heading size="lg" class="text-blue-600">{{ $webhookStats['totalWebhooks'] }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Total Events</flux:text>
                </div>
                
                <div class="text-center p-4 bg-emerald-50 /20 rounded-lg">
                    <flux:heading size="lg" class="text-emerald-600">{{ $webhookStats['processedWebhooks'] }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Processed</flux:text>
                </div>
                
                @if($webhookStats['failedWebhooks'] > 0)
                    <div class="text-center p-4 bg-red-50 /20 rounded-lg">
                        <flux:heading size="lg" class="text-red-600">{{ $webhookStats['failedWebhooks'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Failed</flux:text>
                    </div>
                @endif
                
                @if($webhookStats['pendingWebhooks'] > 0)
                    <div class="text-center p-4 bg-amber-50 /20 rounded-lg">
                        <flux:heading size="lg" class="text-amber-600">{{ $webhookStats['pendingWebhooks'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Pending</flux:text>
                    </div>
                @endif
            </div>

            @if($webhookStats['failedWebhooks'] > 0)
                <div class="mt-4 p-3 bg-red-50 /20 border border-red-200 rounded-lg">
                    <flux:text class="text-red-800 text-sm">
                        <flux:icon icon="exclamation-triangle" class="w-4 h-4 inline mr-1" />
                        {{ $webhookStats['failedWebhooks'] }} webhook events failed processing. Check logs for details.
                    </flux:text>
                </div>
            @endif
        </flux:card>
    @endif

    <!-- Revenue Breakdown (continued) -->
    <div class="grid gap-6 lg:grid-cols-2 mb-6">
        <flux:card>
            <flux:header>
                <flux:heading size="lg">Additional Statistics</flux:heading>
            </flux:header>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <flux:text class="text-gray-600">Fee Rate</flux:text>
                    <flux:text class="font-medium">
                        {{ $totalRevenue > 0 ? number_format(($stripeFees / $totalRevenue) * 100, 1) : 0 }}%
                    </flux:text>
                </div>
                <div class="flex justify-between items-center">
                    <flux:text class="text-gray-600">Failed Rate</flux:text>
                    <flux:text class="font-medium text-red-600">
                        {{ $totalOrders > 0 ? number_format(($failedOrders / $totalOrders) * 100, 1) : 0 }}%
                    </flux:text>
                </div>
                <div class="flex justify-between items-center">
                    <flux:text class="text-gray-600">Pending Rate</flux:text>
                    <flux:text class="font-medium text-amber-600">
                        {{ $totalOrders > 0 ? number_format(($pendingOrders / $totalOrders) * 100, 1) : 0 }}%
                    </flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <flux:header>
                <flux:heading size="lg">Quick Actions</flux:heading>
            </flux:header>
            
            <div class="space-y-3">
                <flux:button variant="outline" class="w-full" href="{{ route('orders.index') }}" wire:navigate>
                    <flux:icon icon="clipboard-document-list" class="w-4 h-4" />
                    View All Orders
                </flux:button>
                
                <flux:button variant="outline" class="w-full" wire:click="exportOrders">
                    <flux:icon icon="arrow-down-tray" class="w-4 h-4" />
                    Export Order Report
                </flux:button>
                
                @if($failedOrders > 0)
                    <div class="p-3 bg-red-50 /20 border border-red-200 rounded-lg text-center">
                        <flux:text class="text-red-800 text-sm">
                            {{ $failedOrders }} orders failed payment
                        </flux:text>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    <!-- Recent Activity -->
    @if($recentActivity->count() > 0)
        <flux:card class="mb-6">
            <flux:header>
                <flux:heading size="lg">Recent Activity (24h)</flux:heading>
            </flux:header>

            <div class="space-y-3">
                @foreach($recentActivity as $activity)
                    <div class="flex items-center justify-between p-3 bg-gray-50  rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-2 h-2 rounded-full {{ $activity->isPaid() ? 'bg-emerald-500' : ($activity->isFailed() ? 'bg-red-500' : 'bg-amber-500') }}"></div>
                            <div>
                                <flux:text class="font-medium">{{ $activity->student->user->name }}</flux:text>
                                <flux:text size="sm" class="text-gray-600">
                                    {{ $activity->formatted_amount }} - {{ $activity->course->name }}
                                </flux:text>
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge :color="$this->getStatusBadgeColor($activity->status)" size="sm">
                                {{ $activity->status_label }}
                            </flux:badge>
                            <flux:text size="sm" class="text-gray-600 block mt-1">
                                {{ $activity->created_at->diffForHumans() }}
                            </flux:text>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <!-- Orders Table -->
    <flux:card>
        <flux:header>
            <flux:heading size="lg">Subscription Orders</flux:heading>
            
            <div class="flex items-center space-x-3">
                <!-- Search -->
                <flux:input 
                    wire:model.live="search" 
                    placeholder="Search orders..."
                    class="w-64"
                />
                
                <!-- Status Filter -->
                <flux:select wire:model.live="statusFilter" placeholder="All Statuses" class="w-40">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    @foreach(Order::getStatuses() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <!-- Billing Reason Filter -->
                <flux:select wire:model.live="typeFilter" placeholder="All Reasons" class="w-40">
                    <flux:select.option value="">All Reasons</flux:select.option>
                    @foreach(Order::getBillingReasons() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <!-- Date Range -->
                <flux:input type="month" wire:model.live="dateRange" class="w-36" />
            </div>
        </flux:header>

        @if($orders->count() > 0)
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
                            <th class="text-left py-3 px-4">Student</th>
                            <th class="text-left py-3 px-4">Order</th>
                            <th class="text-right py-3 px-4">
                                <button wire:click="sortBy('amount')" class="flex items-center space-x-1 hover:text-blue-600 ml-auto">
                                    <span>Amount</span>
                                    @if($sortBy === 'amount')
                                        <flux:icon icon="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                    @endif
                                </button>
                            </th>
                            <th class="text-center py-3 px-4">Reason</th>
                            <th class="text-center py-3 px-4">Status</th>
                            <th class="text-right py-3 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr class="border-b border-gray-100  hover:bg-gray-50 :bg-gray-800/50">
                                <td class="py-3 px-4">
                                    <div class="font-medium">{{ $order->created_at->format('M d, Y') }}</div>
                                    <div class="text-sm text-gray-600">{{ $order->created_at->format('H:i') }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium">{{ $order->student->user->name }}</div>
                                    <div class="text-sm text-gray-600">{{ $order->student->user->email }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <flux:link :href="route('orders.show', $order)" class="font-medium hover:text-blue-600" wire:navigate>
                                        {{ $order->order_number }}
                                    </flux:link>
                                    <div class="text-sm text-gray-600">{{ $order->course->name }}</div>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="font-medium">{{ $order->formatted_amount }}</div>
                                    @if($order->stripe_fee > 0)
                                        <div class="text-sm text-gray-600">Fee: RM {{ number_format($order->stripe_fee, 2) }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <flux:badge color="blue" size="sm">
                                        {{ $order->billing_reason_label }}
                                    </flux:badge>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <flux:badge :color="$this->getStatusBadgeColor($order->status)">
                                        {{ $order->status_label }}
                                    </flux:badge>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <flux:button 
                                        variant="ghost" 
                                        size="sm" 
                                        :href="route('orders.show', $order)" 
                                        wire:navigate
                                    >
                                        <flux:icon icon="eye" class="w-4 h-4" />
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $orders->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <flux:icon icon="clipboard-document-list" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <flux:heading size="md" class="text-gray-600  mb-2">No orders found</flux:heading>
                <flux:text class="text-gray-600">
                    @if($search || $statusFilter || $typeFilter)
                        No orders match your current filters.
                        <button wire:click="$set('search', '')" wire:click="$set('statusFilter', '')" wire:click="$set('typeFilter', '')" class="text-blue-600 hover:underline ml-1">Clear filters</button>
                    @else
                        No subscription orders have been created yet.
                    @endif
                </flux:text>
            </div>
        @endif
    </flux:card>
</div>