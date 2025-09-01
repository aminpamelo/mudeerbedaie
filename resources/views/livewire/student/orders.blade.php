<?php
use App\Models\Order;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $statusFilter = '';
    public $courseFilter = '';

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingCourseFilter()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $student = auth()->user()->student;
        
        $query = Order::where('student_id', $student->id)
            ->with(['course', 'enrollment'])
            ->when($this->statusFilter, function($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->courseFilter, function($query) {
                $query->where('course_id', $this->courseFilter);
            })
            ->orderBy('created_at', 'desc');

        return [
            'orders' => $query->paginate(10),
            'totalPaid' => Order::where('student_id', $student->id)->paid()->sum('amount'),
            'totalOrders' => Order::where('student_id', $student->id)->count(),
            'courses' => Order::where('student_id', $student->id)
                ->with('course')
                ->get()
                ->pluck('course')
                ->unique('id')
                ->values(),
            'orderStatuses' => Order::getStatuses(),
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Orders</flux:heading>
            <flux:text class="mt-2">View your payment history and receipts</flux:text>
        </div>
        <flux:button href="{{ route('student.subscriptions') }}" variant="outline">
            Manage Subscriptions
        </flux:button>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Total Paid</flux:text>
                    <flux:heading size="lg" class="text-green-600">RM {{ number_format($totalPaid, 2) }}</flux:heading>
                </div>
                <flux:icon name="banknotes" class="w-8 h-8 text-green-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Total Orders</flux:text>
                    <flux:heading size="lg">{{ number_format($totalOrders) }}</flux:heading>
                </div>
                <flux:icon name="clipboard-document-list" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    @if($courses->count() > 1 || $orders->count() > 0)
        <flux:card class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    @foreach($orderStatuses as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="courseFilter">
                    <flux:select.option value="">All Courses</flux:select.option>
                    @foreach($courses as $course)
                        <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button 
                    wire:click="$set('statusFilter', ''); $set('courseFilter', '')" 
                    variant="outline"
                >
                    Clear Filters
                </flux:button>
            </div>
        </flux:card>
    @endif

    <!-- Orders List -->
    @if($orders->count() > 0)
        <div class="space-y-4">
            @foreach($orders as $order)
                <flux:card>
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-start gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3">
                                        <flux:heading size="md">{{ $order->course->name }}</flux:heading>
                                        @if($order->isPaid())
                                            <flux:badge variant="success">{{ $order->status_label }}</flux:badge>
                                        @elseif($order->isFailed())
                                            <flux:badge variant="danger">{{ $order->status_label }}</flux:badge>
                                        @elseif($order->isPending())
                                            <flux:badge variant="warning">{{ $order->status_label }}</flux:badge>
                                        @else
                                            <flux:badge variant="gray">{{ $order->status_label }}</flux:badge>
                                        @endif
                                    </div>
                                    
                                    <flux:text class="text-gray-600 mt-1">
                                        Order #{{ $order->order_number }}
                                    </flux:text>
                                    
                                    <div class="mt-3 flex items-center gap-6">
                                        <div>
                                            <flux:text class="text-gray-600">Amount</flux:text>
                                            <flux:text class="font-semibold mt-1">{{ $order->formatted_amount }}</flux:text>
                                        </div>
                                        
                                        <div>
                                            <flux:text class="text-gray-600">Billing Period</flux:text>
                                            <flux:text class="mt-1">{{ $order->getPeriodDescription() }}</flux:text>
                                        </div>
                                        
                                        <div>
                                            <flux:text class="text-gray-600">Date</flux:text>
                                            <flux:text class="mt-1">{{ $order->created_at->format('M j, Y') }}</flux:text>
                                        </div>

                                        @if($order->paid_at)
                                            <div>
                                                <flux:text class="text-gray-600">Paid Date</flux:text>
                                                <flux:text class="mt-1">{{ $order->paid_at->format('M j, Y') }}</flux:text>
                                            </div>
                                        @endif
                                    </div>

                                    @if($order->isFailed() && $order->failure_reason)
                                        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                            <flux:text class="text-red-800 font-medium">Payment Failed</flux:text>
                                            <flux:text class="text-red-700 mt-1">
                                                {{ $order->failure_reason['failure_message'] ?? 'Payment was declined' }}
                                            </flux:text>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 ml-4">
                            <flux:button 
                                href="{{ route('student.orders.show', $order) }}" 
                                variant="outline" 
                                size="sm"
                            >
                                View Details
                            </flux:button>
                            
                            @if($order->receipt_url)
                                <flux:button 
                                    href="{{ $order->receipt_url }}" 
                                    target="_blank"
                                    variant="outline" 
                                    size="sm"
                                >
                                    View Receipt
                                </flux:button>
                            @endif

                            @if($order->isFailed() && $order->enrollment->hasActiveSubscription())
                                <flux:button 
                                    href="{{ route('student.payment-methods') }}" 
                                    variant="primary" 
                                    size="sm"
                                >
                                    Update Payment
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        @if($orders->hasPages())
            <div class="mt-6">
                {{ $orders->links() }}
            </div>
        @endif
    @else
        <flux:card>
            <div class="text-center py-8">
                <flux:icon name="clipboard-document-list" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <flux:text class="text-gray-600">You don't have any orders yet.</flux:text>
                <flux:text size="sm" class="text-gray-500 mt-1">
                    Orders will appear here once you're enrolled in courses with subscription billing.
                </flux:text>
            </div>
        </flux:card>
    @endif
</div>