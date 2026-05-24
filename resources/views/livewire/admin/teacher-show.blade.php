<?php

use App\Models\ClassSession;
use App\Models\Teacher;
use App\Services\Upsell\UpsellPaidOrdersQuery;
use Livewire\Volt\Component;

new class extends Component
{
    public Teacher $teacher;

    public string $upsellDateFrom = '';

    public string $upsellDateTo = '';

    public function mount(Teacher $teacher): void
    {
        $this->teacher = $teacher->load(['user', 'courses.feeSettings', 'courses.enrollments']);
        $this->upsellDateFrom = now()->subDays(90)->toDateString();
        $this->upsellDateTo = now()->toDateString();
    }

    public function deleteTeacher(): void
    {
        $this->teacher->delete();

        session()->flash('success', 'Teacher deleted successfully.');
        $this->redirect(route('teachers.index'));
    }

    public function getUpsellStatsProperty(): array
    {
        $default = [
            'sessions_count' => 0,
            'paid_orders' => 0,
            'paid_revenue' => 0,
            'commission_earned' => 0,
            'commission_paid' => 0,
            'commission_pending' => 0,
            'top_products' => collect(),
        ];

        if (! $this->teacher->user_id) {
            return $default;
        }

        $row = app(UpsellPaidOrdersQuery::class)
            ->forDateRange($this->upsellDateFrom ?: null, $this->upsellDateTo ?: null)
            ->byTeacher()
            ->firstWhere('teacher_id', $this->teacher->user_id);

        return $row ?? $default;
    }

    public function getUpsellSessionsProperty()
    {
        if (! $this->teacher->user_id) {
            return collect();
        }

        return ClassSession::query()
            ->whereJsonContains('upsell_teacher_ids', $this->teacher->user_id)
            ->when($this->upsellDateFrom, fn ($q) => $q->whereDate('session_date', '>=', $this->upsellDateFrom))
            ->when($this->upsellDateTo, fn ($q) => $q->whereDate('session_date', '<=', $this->upsellDateTo))
            ->whereHas('funnelOrders', fn ($q) => $q->whereHas('productOrder', fn ($qq) => $qq
                ->where('payment_status', 'paid')
                ->whereNotIn('status', UpsellPaidOrdersQuery::EXCLUDED_ORDER_STATUSES)))
            ->withCount(['funnelOrders as paid_orders_count' => fn ($q) => $q->whereHas('productOrder', fn ($qq) => $qq
                ->where('payment_status', 'paid')
                ->whereNotIn('status', UpsellPaidOrdersQuery::EXCLUDED_ORDER_STATUSES))])
            ->withSum(['funnelOrders as paid_revenue_sum' => fn ($q) => $q->whereHas('productOrder', fn ($qq) => $qq
                ->where('payment_status', 'paid')
                ->whereNotIn('status', UpsellPaidOrdersQuery::EXCLUDED_ORDER_STATUSES))], 'funnel_revenue')
            ->with(['class', 'class.course'])
            ->orderByDesc('session_date')
            ->limit(50)
            ->get();
    }
};
?>

<div class="space-y-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $teacher->user->name }}</flux:heading>
            <flux:text class="mt-2">Teacher Details</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="primary" href="{{ route('teachers.edit', $teacher) }}">
                Edit Teacher
            </flux:button>
            <flux:button variant="ghost" href="{{ route('teachers.index') }}">
                Back to Teachers
            </flux:button>
        </div>
    </div>

    <!-- Teacher Information -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
            <flux:heading size="lg">Teacher Information</flux:heading>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Teacher ID</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->teacher_id }}</flux:text>
                </div>
                
                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</flux:text>
                    <div class="mt-1">
                        <flux:badge :variant="$teacher->status === 'active' ? 'lime' : 'zinc'">
                            {{ ucfirst($teacher->status) }}
                        </flux:badge>
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Full Name</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->user->name }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Email Address</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->user->email }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">IC Number</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->ic_number ?? 'Not provided' }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone Number</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->phone ?? 'Not provided' }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Joined Date</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $teacher->joined_at ? $teacher->joined_at->format('F j, Y') : 'Not set' }}
                    </flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Created</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->created_at->format('F j, Y') }}</flux:text>
                </div>
            </div>
        </div>
    </div>

    <!-- Banking Information -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
            <flux:heading size="lg">Banking Information</flux:heading>
        </div>
        <div class="p-6">
            @if($teacher->bank_account_holder || $teacher->bank_account_number || $teacher->bank_name)
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Holder Name</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->bank_account_holder ?? 'Not provided' }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Name</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->bank_name ?? 'Not provided' }}</flux:text>
                    </div>

                    <div class="sm:col-span-2">
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                            {{ $teacher->masked_account_number ?? 'Not provided' }}
                        </flux:text>
                        @if($teacher->bank_account_number)
                            <flux:text class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                Account number is encrypted in the database
                            </flux:text>
                        @endif
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon.credit-card class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <flux:text class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">No banking information</flux:text>
                    <flux:text class="text-sm text-gray-500 dark:text-gray-400">No bank account details have been provided for this teacher.</flux:text>
                </div>
            @endif
        </div>
    </div>

    <!-- Assigned Courses -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
            <flux:heading size="lg">Assigned Courses ({{ $teacher->courses->count() }})</flux:heading>
        </div>
        
        @if($teacher->courses->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Course Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Students</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @foreach($teacher->courses as $course)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $course->name }}</div>
                                        @if($course->description)
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ Str::limit($course->description, 60) }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge :variant="$course->status === 'active' ? 'lime' : 'zinc'">
                                        {{ ucfirst($course->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $course->formatted_fee }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $course->enrollments->count() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $course->created_at->format('M j, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('courses.show', $course) }}" class="text-indigo-600 hover:text-indigo-900">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8">
                <flux:icon.academic-cap class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                <flux:text class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">No courses assigned</flux:text>
                <flux:text class="text-sm text-gray-500 dark:text-gray-400">This teacher hasn't been assigned to any courses yet.</flux:text>
            </div>
        @endif
    </div>

    <!-- Upsell Performance -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <flux:heading size="lg">Upsell Performance</flux:heading>
                    <flux:text class="mt-1">Paid orders only. Multi-teacher session revenue is split equally.</flux:text>
                </div>
                <div class="flex gap-2 items-end">
                    <flux:input type="date" wire:model.live="upsellDateFrom" size="sm" label="From" />
                    <flux:input type="date" wire:model.live="upsellDateTo" size="sm" label="To" />
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="border rounded p-4 dark:border-zinc-700">
                    <div class="text-xs text-gray-600 dark:text-gray-400 uppercase">Sessions with Upsell</div>
                    <div class="text-2xl font-bold mt-1">{{ $this->upsellStats['sessions_count'] }}</div>
                </div>
                <div class="border rounded p-4 dark:border-zinc-700">
                    <div class="text-xs text-gray-600 dark:text-gray-400 uppercase">Paid Orders</div>
                    <div class="text-2xl font-bold mt-1">{{ $this->upsellStats['paid_orders'] }}</div>
                </div>
                <div class="border rounded p-4 dark:border-zinc-700">
                    <div class="text-xs text-gray-600 dark:text-gray-400 uppercase">Paid Revenue</div>
                    <div class="text-2xl font-bold mt-1">RM {{ number_format((float) $this->upsellStats['paid_revenue'], 2) }}</div>
                </div>
                <div class="border rounded p-4 dark:border-zinc-700">
                    <div class="text-xs text-gray-600 dark:text-gray-400 uppercase">Commission Earned</div>
                    <div class="text-2xl font-bold mt-1 text-amber-600">RM {{ number_format((float) $this->upsellStats['commission_earned'], 2) }}</div>
                </div>
                <div class="border rounded p-4 dark:border-zinc-700">
                    <div class="text-xs text-gray-600 dark:text-gray-400 uppercase">Commission Paid</div>
                    <div class="text-2xl font-bold mt-1 text-emerald-600">RM {{ number_format((float) ($this->upsellStats['commission_paid'] ?? 0), 2) }}</div>
                </div>
            </div>

            <div class="mt-6">
                <flux:heading size="md" class="mb-2">Upsell Sessions ({{ $this->upsellSessions->count() }})</flux:heading>
                @if($this->upsellSessions->isEmpty())
                    <flux:text class="text-gray-500">No upsell sessions in selected period.</flux:text>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-zinc-700">
                                <th class="text-left py-2 px-3">Date</th>
                                <th class="text-left py-2 px-3">Class</th>
                                <th class="text-right py-2 px-3">Paid Orders</th>
                                <th class="text-right py-2 px-3">Paid Revenue</th>
                                <th class="text-right py-2 px-3">Commission Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->upsellSessions as $session)
                            <tr wire:key="session-{{ $session->id }}" class="border-b dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/40">
                                <td class="py-2 px-3">{{ $session->session_date?->format('Y-m-d') }}</td>
                                <td class="py-2 px-3">{{ $session->class?->title ?? '—' }}</td>
                                <td class="text-right py-2 px-3">{{ $session->paid_orders_count }}</td>
                                <td class="text-right py-2 px-3">RM {{ number_format((float) ($session->paid_revenue_sum ?? 0), 2) }}</td>
                                <td class="text-right py-2 px-3">{{ number_format((float) $session->upsell_teacher_commission_rate, 2) }}%</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg border border-red-200 dark:border-red-900">
        <div class="px-6 py-4 border-b border-red-200 dark:border-red-900">
            <flux:heading size="lg" class="text-red-700">Danger Zone</flux:heading>
        </div>
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="font-medium text-red-700">Delete Teacher</flux:text>
                    <flux:text class="text-sm text-red-600">
                        This action cannot be undone. This will permanently delete the teacher and remove them from all assigned courses.
                    </flux:text>
                </div>
                <flux:button 
                    variant="danger" 
                    wire:click="deleteTeacher"
                    wire:confirm="Are you sure you want to delete this teacher? This action cannot be undone."
                >
                    Delete Teacher
                </flux:button>
            </div>
        </div>
    </div>
</div>