<?php
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $courseFilter = '';

    public $studentFilter = '';

    public $studentSearch = '';

    public $studentName = '';

    public $paymentMethodFilter = '';

    public $enrollmentFilter = '';

    public $sortBy = 'created_at';

    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'courseFilter' => ['except' => ''],
        'studentFilter' => ['except' => ''],
        'paymentMethodFilter' => ['except' => ''],
        'enrollmentFilter' => ['except' => '', 'as' => 'enrollment'],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        if ($this->studentFilter) {
            $this->studentName = Student::with('user:id,name')
                ->find($this->studentFilter)?->user?->name ?? '';
        }
    }

    public function selectStudent($id, $name): void
    {
        $this->studentFilter = (string) $id;
        $this->studentName = $name;
        $this->studentSearch = '';
        $this->resetPage();
    }

    public function clearStudent(): void
    {
        $this->studentFilter = '';
        $this->studentName = '';
        $this->studentSearch = '';
        $this->resetPage();
    }

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

    public function updatingPaymentMethodFilter()
    {
        $this->resetPage();
    }

    public function updatingEnrollmentFilter()
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
        $this->studentSearch = '';
        $this->studentName = '';
        $this->paymentMethodFilter = '';
        $this->enrollmentFilter = '';
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
            ->with(['student.user:id,name,email', 'course:id,name'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('student.user', function ($sub) {
                        $sub->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    })->orWhere('order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('stripe_invoice_id', 'like', '%'.$this->search.'%');
                });
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
            ->when($this->paymentMethodFilter, function ($query) {
                $query->where('payment_method', $this->paymentMethodFilter);
            })
            ->when($this->enrollmentFilter, function ($query) {
                $query->where('enrollment_id', $this->enrollmentFilter);
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        return array_merge([
            'orders' => $query->paginate(15),
            'activeEnrollment' => $this->enrollmentFilter
                ? Enrollment::with(['student.user:id,name', 'course:id,name'])->find($this->enrollmentFilter)
                : null,
            'courses' => Cache::remember('admin.orders.courses', now()->addMinutes(5), function () {
                return Course::orderBy('name')->get(['id', 'name']);
            }),
            'studentResults' => $this->searchStudents(),
            'orderStatuses' => Order::getStatuses(),
            'paymentMethods' => Order::getPaymentMethods(),
        ], $this->summaryStats());
    }

    /**
     * Bounded student lookup for the searchable filter.
     *
     * Never loads the full student table — only up to 50 matches for the
     * current search term — to keep the page memory-safe at any scale.
     *
     * @return \Illuminate\Support\Collection<int, object{id: int, name: string}>
     */
    protected function searchStudents(): \Illuminate\Support\Collection
    {
        $term = trim($this->studentSearch);

        if (strlen($term) < 2) {
            return collect();
        }

        return Student::query()
            ->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            })
            ->with('user:id,name,email')
            ->limit(50)
            ->get(['id', 'user_id'])
            ->map(function ($student) {
                return (object) [
                    'id' => $student->id,
                    'name' => $student->user?->name ?? 'No User Assigned',
                ];
            });
    }

    /**
     * Global summary stats for the header cards.
     *
     * These are full-table aggregates that do not depend on the active filters,
     * so they are cached briefly to keep the page responsive on large datasets.
     *
     * @return array{totalRevenue: float, totalOrders: int, failedOrders: int, stripeOrders: int, manualOrders: int}
     */
    protected function summaryStats(): array
    {
        return Cache::remember('admin.orders.summary_stats', now()->addSeconds(60), function () {
            $byStatus = Order::query()
                ->selectRaw('status, COUNT(*) as orders_count, SUM(amount) as orders_total')
                ->groupBy('status')
                ->get();

            $byMethod = Order::query()
                ->selectRaw('payment_method, COUNT(*) as orders_count')
                ->groupBy('payment_method')
                ->pluck('orders_count', 'payment_method');

            return [
                'totalRevenue' => (float) ($byStatus->firstWhere('status', 'paid')->orders_total ?? 0),
                'totalOrders' => (int) $byStatus->sum('orders_count'),
                'failedOrders' => (int) ($byStatus->firstWhere('status', 'failed')->orders_count ?? 0),
                'stripeOrders' => (int) ($byMethod['stripe'] ?? 0),
                'manualOrders' => (int) ($byMethod['manual'] ?? 0),
            ];
        });
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

    @if($activeEnrollment)
        <flux:callout class="mb-6" icon="funnel" variant="secondary">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:callout.heading>Filtered by enrollment</flux:callout.heading>
                    <flux:callout.text>
                        Showing orders for
                        <strong>{{ $activeEnrollment->student?->user?->name ?? 'Unknown student' }}</strong>
                        in <strong>{{ $activeEnrollment->course?->name ?? 'Unknown course' }}</strong>.
                    </flux:callout.text>
                </div>
                <flux:button size="sm" variant="ghost" wire:click="$set('enrollmentFilter', '')">
                    Clear
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
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

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Stripe Orders</flux:text>
                    <flux:heading size="lg" class="text-blue-600">{{ number_format($stripeOrders) }}</flux:heading>
                </div>
                <flux:icon name="credit-card" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Manual Orders</flux:text>
                    <flux:heading size="lg" class="text-green-600">{{ number_format($manualOrders) }}</flux:heading>
                </div>
                <flux:icon name="banknotes" class="w-8 h-8 text-green-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    <flux:card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
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

            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                @if($studentFilter)
                    <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 dark:border-zinc-700 px-3 py-2">
                        <span class="truncate text-sm">{{ $studentName ?: 'Selected student' }}</span>
                        <button type="button" wire:click="clearStudent" class="text-gray-400 hover:text-gray-600">
                            <flux:icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                @else
                    <flux:input
                        wire:model.live.debounce.300ms="studentSearch"
                        placeholder="Search student..."
                        @focus="open = true"
                        x-on:input="open = true"
                    />
                    <div
                        x-show="open && $wire.studentSearch.length >= 2"
                        x-cloak
                        class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        @forelse($studentResults as $student)
                            <button
                                type="button"
                                wire:key="student-opt-{{ $student->id }}"
                                wire:click="selectStudent({{ $student->id }}, @js($student->name))"
                                @click="open = false"
                                class="block w-full truncate px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-zinc-700"
                            >
                                {{ $student->name }}
                            </button>
                        @empty
                            <div class="px-3 py-2 text-sm text-gray-500">No students found.</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <flux:select wire:model.live="paymentMethodFilter">
                <flux:select.option value="">All Payment Methods</flux:select.option>
                @foreach($paymentMethods as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
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
                    <tr class="border-b border-gray-200 dark:border-zinc-700">
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
                        <th class="text-left py-3 px-4">Payment Method</th>
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
                        <tr class="border-b border-gray-100 transition-colors hover:bg-gray-50 dark:border-zinc-700/60 dark:hover:bg-zinc-800/60">
                            <td class="py-3 px-4">
                                <flux:text class="font-mono text-sm">{{ $order->order_number }}</flux:text>
                                @if($order->stripe_invoice_id)
                                    <flux:text size="xs" class="text-gray-500 block">{{ $order->stripe_invoice_id }}</flux:text>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <flux:text>{{ $order->student->user?->name ?? 'No User' }}</flux:text>
                                <flux:text size="xs" class="text-gray-500 block">{{ $order->student->user?->email ?? 'N/A' }}</flux:text>
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
                                <div class="flex items-center">
                                    @if($order->payment_method === 'stripe')
                                        <flux:icon name="credit-card" class="w-4 h-4 mr-2 text-blue-500" />
                                    @else
                                        <flux:icon name="banknotes" class="w-4 h-4 mr-2 text-green-500" />
                                    @endif
                                    <flux:text size="sm">{{ $order->payment_method_label }}</flux:text>
                                </div>
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
                            <td colspan="9" class="py-8 text-center">
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