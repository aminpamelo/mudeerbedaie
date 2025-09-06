<?php

use App\Models\Enrollment;
use App\Models\Course;
use App\Models\Student;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $courseFilter = '';
    public $subscriptionStatusFilter = '';
    public $hasSubscriptionFilter = '';
    public $perPage = 10;

    public function mount(): void
    {
        //
    }

    public function with(): array
    {
        $query = Enrollment::query()
            ->with(['student.user', 'course', 'course.feeSettings', 'enrolledBy', 'orders' => function($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->whereHas('student.user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->orWhereHas('student', function($q) {
                $q->where('student_id', 'like', '%' . $this->search . '%');
            })
            ->orWhereHas('course', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->courseFilter) {
            $query->where('course_id', $this->courseFilter);
        }
        
        if ($this->subscriptionStatusFilter) {
            $query->where('subscription_status', $this->subscriptionStatusFilter);
        }
        
        if ($this->hasSubscriptionFilter === 'yes') {
            $query->whereNotNull('stripe_subscription_id');
        } elseif ($this->hasSubscriptionFilter === 'no') {
            $query->whereNull('stripe_subscription_id');
        }

        return [
            'enrollments' => $query->paginate($this->perPage),
            'courses' => Course::where('status', 'active')->get(),
            'totalEnrollments' => Enrollment::count(),
            'activeEnrollments' => Enrollment::active()->count(),
            'completedEnrollments' => Enrollment::completed()->count(),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCourseFilter(): void
    {
        $this->resetPage();
    }
    
    public function updatingSubscriptionStatusFilter(): void
    {
        $this->resetPage();
    }
    
    public function updatingHasSubscriptionFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->courseFilter = '';
        $this->subscriptionStatusFilter = '';
        $this->hasSubscriptionFilter = '';
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Enrollments</flux:heading>
            <flux:text class="mt-2">Manage student course enrollments</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('enrollments.create') }}" icon="user-plus">
            New Enrollment
        </flux:button>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 p-3">
                        <flux:icon.clipboard class="h-6 w-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $totalEnrollments }}</p>
                        <p class="text-sm text-gray-500">Total Enrollments</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 p-3">
                        <flux:icon.check-circle class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $activeEnrollments }}</p>
                        <p class="text-sm text-gray-500">Active Enrollments</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-emerald-50 p-3">
                        <flux:icon.check-badge class="h-6 w-6 text-emerald-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $completedEnrollments }}</p>
                        <p class="text-sm text-gray-500">Completed</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Search and Filters -->
        <flux:card>
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <flux:input 
                            wire:model.live.debounce.300ms="search" 
                            placeholder="Search by student name, email, student ID, or course..." 
                            icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="statusFilter" placeholder="Filter by status">
                            <flux:select.option value="">All Statuses</flux:select.option>
                            <flux:select.option value="enrolled">Enrolled</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="completed">Completed</flux:select.option>
                            <flux:select.option value="dropped">Dropped</flux:select.option>
                            <flux:select.option value="suspended">Suspended</flux:select.option>
                            <flux:select.option value="pending">Pending</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="courseFilter" placeholder="Filter by course">
                            <flux:select.option value="">All Courses</flux:select.option>
                            @foreach($courses as $course)
                                <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="subscriptionStatusFilter" placeholder="Subscription status">
                            <flux:select.option value="">All Subscriptions</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="trialing">Trialing</flux:select.option>
                            <flux:select.option value="past_due">Past Due</flux:select.option>
                            <flux:select.option value="canceled">Canceled</flux:select.option>
                            <flux:select.option value="unpaid">Unpaid</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="hasSubscriptionFilter" placeholder="Has subscription">
                            <flux:select.option value="">All Enrollments</flux:select.option>
                            <flux:select.option value="yes">With Subscription</flux:select.option>
                            <flux:select.option value="no">No Subscription</flux:select.option>
                        </flux:select>
                    </div>
                    @if($search || $statusFilter || $courseFilter || $subscriptionStatusFilter || $hasSubscriptionFilter)
                        <flux:button wire:click="clearFilters" variant="ghost">
                            Clear Filters
                        </flux:button>
                    @endif
                </div>
            </div>

            <!-- Enrollments Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscription</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Fee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Payment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Payment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($enrollments as $enrollment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <flux:avatar size="sm" class="mr-3">
                                            {{ $enrollment->student->user->initials() }}
                                        </flux:avatar>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $enrollment->student->user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $enrollment->student->student_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $enrollment->course->name }}</div>
                                    <div class="text-sm text-gray-500">{{ Str::limit($enrollment->course->description, 50) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge :class="$enrollment->status_badge_class">
                                        {{ ucfirst($enrollment->status) }}
                                    </flux:badge>
                                </td>
                                <!-- Subscription Status -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($enrollment->stripe_subscription_id)
                                        <flux:badge :class="match($enrollment->subscription_status) {
                                            'active' => 'badge-green',
                                            'trialing' => 'badge-blue',
                                            'past_due' => 'badge-yellow',
                                            'canceled' => 'badge-red',
                                            'unpaid' => 'badge-red',
                                            default => 'badge-gray'
                                        }" size="sm">
                                            {{ $enrollment->getSubscriptionStatusLabel() }}
                                        </flux:badge>
                                    @else
                                        <flux:badge variant="ghost" size="sm">No Subscription</flux:badge>
                                    @endif
                                </td>
                                <!-- Monthly Fee -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($enrollment->course->feeSettings)
                                        {{ $enrollment->course->feeSettings->formatted_fee }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <!-- Next Payment -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($enrollment->hasActiveSubscription())
                                        @php $nextPayment = $enrollment->getFormattedNextPaymentDate(); @endphp
                                        @if($nextPayment)
                                            <span class="text-green-600">{{ $nextPayment }}</span>
                                        @else
                                            <span class="text-gray-400">Not scheduled</span>
                                        @endif
                                    @elseif($enrollment->isSubscriptionPastDue())
                                        <span class="text-red-600">Overdue</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <!-- Last Payment -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($enrollment->orders->isNotEmpty())
                                        @php $lastOrder = $enrollment->orders->first(); @endphp
                                        @if($lastOrder->isPaid())
                                            {{ $lastOrder->created_at->diffForHumans() }}
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <flux:button size="sm" variant="ghost" href="{{ route('enrollments.show', $enrollment) }}">
                                            View
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" href="{{ route('enrollments.edit', $enrollment) }}">
                                            Edit
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <flux:icon.clipboard class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No enrollments found</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        @if($search || $statusFilter || $courseFilter || $subscriptionStatusFilter || $hasSubscriptionFilter)
                                            Try adjusting your search or filter criteria.
                                        @else
                                            Get started by enrolling your first student.
                                        @endif
                                    </p>
                                    @if(!$search && !$statusFilter && !$courseFilter && !$subscriptionStatusFilter && !$hasSubscriptionFilter)
                                        <div class="mt-6">
                                            <flux:button variant="primary" href="{{ route('enrollments.create') }}">
                                                New Enrollment
                                            </flux:button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($enrollments->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $enrollments->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>