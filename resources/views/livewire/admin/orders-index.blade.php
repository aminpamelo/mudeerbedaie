<?php
use App\Models\Order;
use App\Models\Student;
use App\Models\Course;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $courseFilter = '';
    public $studentFilter = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'courseFilter' => ['except' => ''],
        'studentFilter' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingCourseFilter()
    {
        $this->resetPage();
    }

    public function updatingStudentFilter()
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
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->courseFilter = '';
        $this->studentFilter = '';
        $this->resetPage();
    }

    public function exportOrders()
    {
        // Could implement CSV export here
        session()->flash('info', 'Export functionality will be implemented soon.');
    }

    public function with(): array
    {
        $query = Order::query()
            ->with(['student.user', 'course', 'enrollment'])
            ->when($this->search, function ($query) {
                $query->whereHas('student.user', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                })->orWhere('order_number', 'like', '%' . $this->search . '%')
                  ->orWhere('stripe_invoice_id', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->courseFilter, function ($query) {
                $query->where('course_id', $this->courseFilter);
            })
            ->when($this->studentFilter, function ($query) {
                $query->where('student_id', $this->studentFilter);
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        return [
            'orders' => $query->paginate(15),
            'totalRevenue' => Order::paid()->sum('amount'),
            'totalOrders' => Order::count(),
            'failedOrders' => Order::failed()->count(),
            'courses' => Course::orderBy('name')->get(['id', 'name']),
            'students' => Student::with('user')->get()->map(function ($student) {
                return (object) [
                    'id' => $student->id,
                    'name' => $student->user->name,
                ];
            }),
            'orderStatuses' => Order::getStatuses(),
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Orders</flux:heading>
            <flux:text class="mt-2">Manage subscription orders and payments</flux:text>
        </div>
        <flux:button wire:click="exportOrders" variant="outline">
            <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
            Export
        </flux:button>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Total Revenue</flux:text>
                    <flux:heading size="lg" class="text-green-600">RM {{ number_format($totalRevenue, 2) }}</flux:heading>
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

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Failed Orders</flux:text>
                    <flux:heading size="lg" class="text-red-600">{{ number_format($failedOrders) }}</flux:heading>
                </div>
                <flux:icon name="exclamation-triangle" class="w-8 h-8 text-red-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    <flux:card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <flux:input wire:model.live="search" placeholder="Search orders..." />
            
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

            <flux:select wire:model.live="studentFilter">
                <flux:select.option value="">All Students</flux:select.option>
                @foreach($students as $student)
                    <flux:select.option value="{{ $student->id }}">{{ $student->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button wire:click="clearFilters" variant="outline">Clear Filters</flux:button>
        </div>
    </flux:card>

    <!-- Orders Table -->
    <flux:card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4">
                            <button wire:click="sortBy('order_number')" class="flex items-center gap-1 hover:text-blue-600">
                                Order Number
                                @if($sortBy === 'order_number')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </button>
                        </th>
                        <th class="text-left py-3 px-4">Student</th>
                        <th class="text-left py-3 px-4">Course</th>
                        <th class="text-left py-3 px-4">
                            <button wire:click="sortBy('amount')" class="flex items-center gap-1 hover:text-blue-600">
                                Amount
                                @if($sortBy === 'amount')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </button>
                        </th>
                        <th class="text-left py-3 px-4">Status</th>
                        <th class="text-left py-3 px-4">Period</th>
                        <th class="text-left py-3 px-4">
                            <button wire:click="sortBy('created_at')" class="flex items-center gap-1 hover:text-blue-600">
                                Date
                                @if($sortBy === 'created_at')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </button>
                        </th>
                        <th class="text-left py-3 px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <flux:text class="font-mono text-sm">{{ $order->order_number }}</flux:text>
                                @if($order->stripe_invoice_id)
                                    <flux:text size="xs" class="text-gray-500 block">{{ $order->stripe_invoice_id }}</flux:text>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <flux:text>{{ $order->student->user->name }}</flux:text>
                                <flux:text size="xs" class="text-gray-500 block">{{ $order->student->user->email }}</flux:text>
                            </td>
                            <td class="py-3 px-4">
                                <flux:text>{{ $order->course->name }}</flux:text>
                            </td>
                            <td class="py-3 px-4">
                                <flux:text class="font-semibold">{{ $order->formatted_amount }}</flux:text>
                                @if($order->currency !== 'MYR')
                                    <flux:text size="xs" class="text-gray-500">({{ strtoupper($order->currency) }})</flux:text>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($order->isPaid())
                                    <flux:badge variant="success">{{ $order->status_label }}</flux:badge>
                                @elseif($order->isFailed())
                                    <flux:badge variant="danger">{{ $order->status_label }}</flux:badge>
                                @elseif($order->isPending())
                                    <flux:badge variant="warning">{{ $order->status_label }}</flux:badge>
                                @else
                                    <flux:badge variant="gray">{{ $order->status_label }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <flux:text size="sm">{{ $order->getPeriodDescription() }}</flux:text>
                            </td>
                            <td class="py-3 px-4">
                                <flux:text size="sm">{{ $order->created_at->format('M j, Y') }}</flux:text>
                                <flux:text size="xs" class="text-gray-500 block">{{ $order->created_at->format('g:i A') }}</flux:text>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex gap-2">
                                    @if($order->receipt_url)
                                        <flux:button href="{{ $order->receipt_url }}" target="_blank" variant="outline" size="sm">
                                            Receipt
                                        </flux:button>
                                    @endif
                                    <flux:button href="{{ route('orders.show', $order) }}" variant="outline" size="sm">
                                        View
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center">
                                <flux:text class="text-gray-500">No orders found.</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="mt-6">
                {{ $orders->links() }}
            </div>
        @endif
    </flux:card>
</div>