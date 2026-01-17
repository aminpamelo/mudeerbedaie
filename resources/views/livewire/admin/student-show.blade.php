<?php

use App\Models\Student;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Student $student;

    public string $activeTab = 'overview';

    public function mount(): void
    {
        $this->student->load([
            'user',
            'enrollments.course',
            'activeEnrollments.course',
            'completedEnrollments.course',
            'user.paymentMethods',
            'classAttendances.session.class.course',
            'classAttendances' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(20);
            },
            'orders' => function ($query) {
                $query->with(['items', 'platform', 'platformAccount'])
                    ->latest()
                    ->limit(20);
            },
            'paidOrders',
            'pendingOrders',
            'failedOrders',
        ]);
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function getAttendanceStatsProperty(): array
    {
        $attendances = $this->student->classAttendances;

        return [
            'total' => $attendances->count(),
            'present' => $attendances->where('status', 'present')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'late' => $attendances->where('status', 'late')->count(),
            'excused' => $attendances->where('status', 'excused')->count(),
            'rate' => $attendances->count() > 0 ? round(($attendances->where('status', 'present')->count() / $attendances->count()) * 100, 1) : 0,
        ];
    }

    public function getOrderStatsProperty(): array
    {
        return [
            'total' => $this->student->order_count,
            'paid' => $this->student->paid_order_count,
            'pending' => $this->student->pendingOrders->count(),
            'failed' => $this->student->failed_order_count,
            'total_paid_amount' => $this->student->total_paid_amount,
            'total_pending_amount' => $this->student->total_pending_amount,
        ];
    }

    public function getRecentActivityProperty(): array
    {
        $activities = collect();

        // Recent orders
        $this->student->orders->take(5)->each(function ($order) use ($activities) {
            $activities->push([
                'type' => 'order',
                'icon' => 'shopping-bag',
                'color' => 'text-blue-600',
                'title' => 'Order '.$order->display_order_id,
                'description' => 'RM '.number_format($order->total_amount, 2).' - '.ucfirst($order->status),
                'timestamp' => $order->order_date ?? $order->created_at,
                'url' => route('admin.orders.show', $order),
            ]);
        });

        // Recent attendance
        $this->student->classAttendances->take(5)->each(function ($attendance) use ($activities) {
            $activities->push([
                'type' => 'attendance',
                'icon' => 'academic-cap',
                'color' => match ($attendance->status) {
                    'present' => 'text-green-600',
                    'absent' => 'text-red-600',
                    'late' => 'text-yellow-600',
                    default => 'text-gray-600',
                },
                'title' => $attendance->class->title ?? 'Class Attendance',
                'description' => ucfirst($attendance->status).($attendance->class->date_time ? ' - '.$attendance->class->date_time->format('M d, Y') : ''),
                'timestamp' => $attendance->created_at,
                'url' => null,
            ]);
        });

        return $activities->sortByDesc('timestamp')->take(10)->values()->toArray();
    }
}; ?>

<div>
    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <flux:avatar size="xl">
                {{ $student->user->initials() }}
            </flux:avatar>
            <div>
                <flux:heading size="xl">{{ $student->user->name }}</flux:heading>
                <div class="mt-1 flex items-center gap-3">
                    <flux:text class="text-gray-600">{{ $student->student_id }}</flux:text>
                    <flux:badge :class="match($student->status) {
                        'active' => 'badge-green',
                        'inactive' => 'badge-gray',
                        'graduated' => 'badge-blue',
                        'suspended' => 'badge-red',
                        default => 'badge-gray'
                    }">
                        {{ ucfirst($student->status) }}
                    </flux:badge>
                </div>
            </div>
        </div>
        <div class="flex gap-3">
            <flux:button variant="ghost" href="{{ route('students.index') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                    Back to Students
                </div>
            </flux:button>
            <flux:button variant="outline" href="{{ route('admin.customer-service.return-refunds.create') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                    Return & Refund
                </div>
            </flux:button>
            <flux:button variant="primary" href="{{ route('students.edit', $student) }}" icon="pencil">
                Edit Student
            </flux:button>
        </div>
    </div>

    {{-- Quick Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-blue-600">{{ $student->enrollments->count() }}</p>
                <p class="text-sm text-gray-500 mt-1">Total Enrollments</p>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-green-600">{{ $student->activeEnrollments->count() }}</p>
                <p class="text-sm text-gray-500 mt-1">Active Courses</p>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-purple-600">{{ $this->attendanceStats['total'] }}</p>
                <p class="text-sm text-gray-500 mt-1">Classes Attended</p>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-indigo-600">{{ $this->orderStats['total'] }}</p>
                <p class="text-sm text-gray-500 mt-1">Total Orders</p>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-emerald-600">RM {{ number_format($this->orderStats['total_paid_amount'], 2) }}</p>
                <p class="text-sm text-gray-500 mt-1">Total Paid</p>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold {{ $this->attendanceStats['rate'] >= 80 ? 'text-green-600' : ($this->attendanceStats['rate'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ $this->attendanceStats['rate'] }}%
                </p>
                <p class="text-sm text-gray-500 mt-1">Attendance Rate</p>
            </div>
        </flux:card>
    </div>

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-6">
            <button
                wire:click="setActiveTab('overview')"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors
                    {{ $activeTab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <flux:icon name="home" class="w-4 h-4" />
                Overview
            </button>

            <button
                wire:click="setActiveTab('orders')"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors
                    {{ $activeTab === 'orders' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <flux:icon name="shopping-bag" class="w-4 h-4" />
                Orders
                @if($this->orderStats['total'] > 0)
                    <flux:badge size="sm" class="badge-blue">{{ $this->orderStats['total'] }}</flux:badge>
                @endif
            </button>

            <button
                wire:click="setActiveTab('enrollments')"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors
                    {{ $activeTab === 'enrollments' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <flux:icon name="book-open" class="w-4 h-4" />
                Enrollments
                @if($student->activeEnrollments->count() > 0)
                    <flux:badge size="sm" class="badge-green">{{ $student->activeEnrollments->count() }}</flux:badge>
                @endif
            </button>

            <button
                wire:click="setActiveTab('attendance')"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors
                    {{ $activeTab === 'attendance' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <flux:icon name="academic-cap" class="w-4 h-4" />
                Attendance
            </button>

            <button
                wire:click="setActiveTab('payment-methods')"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors
                    {{ $activeTab === 'payment-methods' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <flux:icon name="credit-card" class="w-4 h-4" />
                Payment Methods
            </button>

            <button
                wire:click="setActiveTab('personal-info')"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors
                    {{ $activeTab === 'personal-info' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <flux:icon name="user" class="w-4 h-4" />
                Personal Info
            </button>
        </nav>
    </div>

    {{-- Tab Content --}}
    <div class="mt-6">
        {{-- Overview Tab --}}
        @if($activeTab === 'overview')
            <div class="space-y-6">
                {{-- Recent Activity --}}
                <flux:card>
                    <flux:heading size="lg">Recent Activity</flux:heading>
                    <flux:text class="text-gray-600">Latest orders and class attendance</flux:text>

                    <div class="mt-6 flow-root">
                        <ul role="list" class="-mb-8">
                            @forelse($this->recentActivity as $index => $activity)
                                <li>
                                    <div class="relative pb-8">
                                        @if(!$loop->last)
                                            <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        @endif
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full {{ $activity['color'] }} bg-gray-100 flex items-center justify-center ring-8 ring-white">
                                                    <flux:icon :name="$activity['icon']" class="h-4 w-4" />
                                                </span>
                                            </div>
                                            <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                <div class="flex-1 min-w-0">
                                                    @if($activity['url'])
                                                        <a href="{{ $activity['url'] }}" class="group flex items-start justify-between hover:bg-gray-50 -mx-2 px-2 py-1 rounded transition-colors">
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-gray-900 group-hover:text-blue-600 transition-colors">{{ $activity['title'] }}</p>
                                                                <p class="text-sm text-gray-500">{{ $activity['description'] }}</p>
                                                            </div>
                                                            <flux:icon name="chevron-right" class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors flex-shrink-0 ml-2 mt-0.5" />
                                                        </a>
                                                    @else
                                                        <div>
                                                            <p class="text-sm font-medium text-gray-900">{{ $activity['title'] }}</p>
                                                            <p class="text-sm text-gray-500">{{ $activity['description'] }}</p>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="whitespace-nowrap text-right text-sm text-gray-500 pt-1">
                                                    <time datetime="{{ $activity['timestamp'] }}">{{ $activity['timestamp']->diffForHumans() }}</time>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="text-center py-8">
                                    <flux:text class="text-gray-500">No recent activity</flux:text>
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </flux:card>

                {{-- Quick Order Summary --}}
                @if($this->orderStats['total'] > 0)
                    <flux:card>
                        <flux:heading size="lg">Order Summary</flux:heading>

                        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm font-medium text-blue-600">Total Orders</p>
                                <p class="text-2xl font-bold text-blue-700 mt-1">{{ $this->orderStats['total'] }}</p>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <p class="text-sm font-medium text-green-600">Paid Orders</p>
                                <p class="text-2xl font-bold text-green-700 mt-1">{{ $this->orderStats['paid'] }}</p>
                            </div>
                            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                                <p class="text-sm font-medium text-yellow-600">Pending Orders</p>
                                <p class="text-2xl font-bold text-yellow-700 mt-1">{{ $this->orderStats['pending'] }}</p>
                            </div>
                            <div class="text-center p-4 bg-emerald-50 rounded-lg">
                                <p class="text-sm font-medium text-emerald-600">Total Revenue</p>
                                <p class="text-2xl font-bold text-emerald-700 mt-1">RM {{ number_format($this->orderStats['total_paid_amount'], 2) }}</p>
                            </div>
                        </div>
                    </flux:card>
                @endif
            </div>
        @endif

        {{-- Orders Tab --}}
        @if($activeTab === 'orders')
            <flux:card>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <flux:heading size="lg">Order History</flux:heading>
                        <flux:text class="text-gray-600">All orders from this student</flux:text>
                    </div>
                </div>

                @if($student->orders->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($student->orders as $order)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $order->display_order_id }}</div>
                                            @if($order->platform)
                                                <div class="text-xs text-gray-500">{{ $order->platform->name }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ $order->order_date?->format('M d, Y') ?? $order->created_at->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500">{{ $order->order_date?->format('g:i A') ?? $order->created_at->format('g:i A') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <flux:badge size="sm" :class="match($order->status) {
                                                'completed', 'delivered' => 'badge-green',
                                                'pending', 'processing' => 'badge-yellow',
                                                'cancelled' => 'badge-red',
                                                'shipped' => 'badge-blue',
                                                default => 'badge-gray'
                                            }">
                                                {{ ucfirst($order->status) }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $order->items->count() }} item{{ $order->items->count() !== 1 ? 's' : '' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            RM {{ number_format($order->total_amount, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $order->source ? ucfirst(str_replace('_', ' ', $order->source)) : 'Manual' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center gap-2">
                                                <flux:button size="sm" variant="ghost" href="{{ route('admin.orders.show', $order) }}">
                                                    View
                                                </flux:button>
                                                @if(in_array($order->status, ['delivered', 'shipped', 'completed']))
                                                    <flux:button size="sm" variant="ghost" href="{{ route('admin.customer-service.return-refunds.create', ['order_id' => $order->id]) }}">
                                                        <div class="flex items-center">
                                                            <flux:icon name="arrow-path" class="w-3 h-3 mr-1" />
                                                            Refund
                                                        </div>
                                                    </flux:button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon name="shopping-bag" class="mx-auto h-12 w-12 text-gray-400" />
                        <flux:heading size="lg" class="mt-2">No orders yet</flux:heading>
                        <flux:text class="mt-1 text-gray-500">This student hasn't placed any orders yet.</flux:text>
                    </div>
                @endif
            </flux:card>
        @endif

        {{-- Enrollments Tab --}}
        @if($activeTab === 'enrollments')
            <div class="space-y-6">
                {{-- Current Enrollments --}}
                @if($student->activeEnrollments->count() > 0)
                    <flux:card>
                        <flux:heading size="lg">Current Enrollments</flux:heading>
                        <flux:text class="text-gray-600">Active course enrollments</flux:text>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($student->activeEnrollments as $enrollment)
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $enrollment->course->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $enrollment->course->description }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <flux:badge :class="$enrollment->status_badge_class">
                                                    {{ ucfirst($enrollment->status) }}
                                                </flux:badge>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $enrollment->enrollment_date->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <flux:button size="sm" variant="ghost" href="{{ route('enrollments.show', $enrollment) }}">
                                                    View Details
                                                </flux:button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </flux:card>
                @endif

                {{-- Enrollment History --}}
                @if($student->enrollments->count() > $student->activeEnrollments->count())
                    <flux:card>
                        <flux:heading size="lg">Enrollment History</flux:heading>
                        <flux:text class="text-gray-600">Past course enrollments</flux:text>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($student->enrollments->reject(fn($enrollment) => $enrollment->isActive()) as $enrollment)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $enrollment->course->name }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <flux:badge :class="$enrollment->status_badge_class">
                                                    {{ ucfirst($enrollment->status) }}
                                                </flux:badge>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $enrollment->enrollment_date->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $enrollment->completion_date?->format('M d, Y') ?? 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </flux:card>
                @endif

                @if($student->enrollments->count() === 0)
                    <flux:card>
                        <div class="text-center py-12">
                            <flux:icon name="book-open" class="mx-auto h-12 w-12 text-gray-400" />
                            <flux:heading size="lg" class="mt-2">No enrollments</flux:heading>
                            <flux:text class="mt-1 text-gray-500">This student hasn't enrolled in any courses yet.</flux:text>
                        </div>
                    </flux:card>
                @endif
            </div>
        @endif

        {{-- Attendance Tab --}}
        @if($activeTab === 'attendance')
            <div class="space-y-6">
                {{-- Attendance Statistics --}}
                @if($this->attendanceStats['total'] > 0)
                    <flux:card>
                        <flux:heading size="lg">Attendance Overview</flux:heading>

                        <div class="mt-6 grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600">{{ $this->attendanceStats['present'] }}</p>
                                <p class="text-sm text-gray-500 mt-1">Present</p>
                            </div>

                            <div class="text-center">
                                <p class="text-2xl font-bold text-red-600">{{ $this->attendanceStats['absent'] }}</p>
                                <p class="text-sm text-gray-500 mt-1">Absent</p>
                            </div>

                            <div class="text-center">
                                <p class="text-2xl font-bold text-yellow-600">{{ $this->attendanceStats['late'] }}</p>
                                <p class="text-sm text-gray-500 mt-1">Late</p>
                            </div>

                            <div class="text-center">
                                <p class="text-2xl font-bold text-blue-600">{{ $this->attendanceStats['excused'] }}</p>
                                <p class="text-sm text-gray-500 mt-1">Excused</p>
                            </div>

                            <div class="text-center">
                                <p class="text-2xl font-bold text-purple-600">{{ $this->attendanceStats['rate'] }}%</p>
                                <p class="text-sm text-gray-500 mt-1">Attendance Rate</p>
                            </div>
                        </div>

                        <div class="mt-6 bg-gray-200 rounded-full h-3">
                            <div
                                class="bg-green-500 h-3 rounded-full transition-all duration-300"
                                style="width: {{ $this->attendanceStats['rate'] }}%"
                            ></div>
                        </div>
                    </flux:card>

                    {{-- Attendance Records --}}
                    <flux:card>
                        <flux:heading size="lg">Attendance Records</flux:heading>
                        <flux:text class="text-gray-600">Recent class attendance</flux:text>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($student->classAttendances as $attendance)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $attendance->class->title }}</div>
                                                <div class="text-sm text-gray-500">{{ $attendance->class->formatted_duration }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $attendance->class->course->name }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $attendance->class->date_time->format('M d, Y') }}</div>
                                                <div class="text-sm text-gray-500">{{ $attendance->class->date_time->format('g:i A') }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <flux:badge size="sm" :class="$attendance->status_badge_class">
                                                    {{ $attendance->status_label }}
                                                </flux:badge>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $attendance->formatted_checked_in_time ?: '-' }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <div class="max-w-xs truncate">
                                                    {{ $attendance->teacher_remarks ?: '-' }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </flux:card>
                @else
                    <flux:card>
                        <div class="text-center py-12">
                            <flux:icon name="academic-cap" class="mx-auto h-12 w-12 text-gray-400" />
                            <flux:heading size="lg" class="mt-2">No attendance records</flux:heading>
                            <flux:text class="mt-1 text-gray-500">This student hasn't attended any classes yet.</flux:text>
                        </div>
                    </flux:card>
                @endif
            </div>
        @endif

        {{-- Payment Methods Tab --}}
        @if($activeTab === 'payment-methods')
            <flux:card>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <flux:heading size="lg">Payment Methods</flux:heading>
                        <flux:text class="text-gray-600">Manage saved payment methods for subscriptions</flux:text>
                    </div>
                    <flux:button variant="outline" icon="credit-card" href="{{ route('admin.students.payment-methods', $student) }}">
                        Manage Payment Methods
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Payment Methods</p>
                        <p class="text-2xl font-semibold text-blue-600">{{ $student->user->paymentMethods->count() }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Active Methods</p>
                        <p class="text-2xl font-semibold text-green-600">{{ $student->user->paymentMethods->where('is_active', true)->count() }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Default Method</p>
                        @php $defaultMethod = $student->user->paymentMethods->where('is_default', true)->first(); @endphp
                        @if($defaultMethod)
                            <p class="text-sm text-gray-900">{{ $defaultMethod->display_name }}</p>
                            <p class="text-xs text-gray-500">{{ $defaultMethod->is_expired ? 'Expired' : 'Active' }}</p>
                        @else
                            <p class="text-sm text-red-600">No default method</p>
                            <p class="text-xs text-gray-500">Subscription creation blocked</p>
                        @endif
                    </div>
                </div>

                @if($student->user->paymentMethods->isEmpty())
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-start gap-3">
                            <flux:icon.exclamation-triangle class="w-5 h-5 text-amber-600 mt-0.5" />
                            <div>
                                <flux:text class="font-medium text-amber-800">No Payment Methods</flux:text>
                                <flux:text class="text-amber-700 mt-1">
                                    This student cannot create subscriptions until a payment method is added.
                                    Click "Manage Payment Methods" to add one.
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @endif
            </flux:card>
        @endif

        {{-- Personal Info Tab --}}
        @if($activeTab === 'personal-info')
            <flux:card>
                <flux:heading size="lg">Personal Information</flux:heading>

                <div class="mt-6 space-y-6">
                    <div class="flex items-center gap-4">
                        <flux:avatar size="xl">
                            {{ $student->user->initials() }}
                        </flux:avatar>
                        <div>
                            <p class="text-lg font-medium">{{ $student->user->name }}</p>
                            <p class="text-gray-500">{{ $student->user->email }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Student ID</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $student->student_id }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Phone</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $student->phone ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Date of Birth</p>
                            <p class="text-sm text-gray-900 mt-1">
                                @if($student->date_of_birth)
                                    {{ $student->date_of_birth->format('M d, Y') }} ({{ $student->age }} years old)
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Gender</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $student->gender ? ucfirst($student->gender) : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Nationality</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $student->nationality ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">IC Number</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $student->ic_number ?? 'N/A' }}</p>
                        </div>
                    </div>

                    @if($student->address)
                        <div class="pt-6 border-t">
                            <p class="text-sm font-medium text-gray-500">Address</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $student->address }}</p>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endif
    </div>
</div>