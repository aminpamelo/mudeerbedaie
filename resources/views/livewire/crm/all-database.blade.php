<?php

use App\Models\Student;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $countryFilter = '';
    public $perPage = 10;

    public function mount(): void
    {
        //
    }

    public function with(): array
    {
        $query = Student::query()
            ->with([
                'user',
                'orders' => fn($q) => $q->whereNotIn('status', ['cancelled', 'refunded']),
                'orders.items.product',
                'activeClasses',
            ])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->orWhere('student_id', 'like', '%' . $this->search . '%')
            ->orWhere('phone', 'like', '%' . $this->search . '%');
        }

        if ($this->countryFilter) {
            $query->where('country', $this->countryFilter);
        }

        $students = $query->paginate($this->perPage);

        // Calculate aggregated stats - count revenue from non-cancelled orders
        $totalRevenue = \App\Models\ProductOrder::query()
            ->whereNotNull('student_id')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('total_amount');

        return [
            'students' => $students,
            'totalContacts' => Student::count(),
            'countries' => Student::distinct()->pluck('country')->filter(),
            'totalRevenue' => $totalRevenue,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCountryFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->countryFilter = '';
        $this->resetPage();
    }

    public function exportContacts(): void
    {
        // Store current filters in session for the download route
        session([
            'crm_export_search' => $this->search,
            'crm_export_country_filter' => $this->countryFilter
        ]);

        // Redirect to the download route
        $this->redirect(route('crm.export'));
    }

}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">All Database</flux:heading>
            <flux:text class="mt-2">Complete student contact database with revenue tracking</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="outline" wire:click="exportContacts">
                <div class="flex items-center justify-center">
                    <flux:icon name="document-arrow-down" class="w-4 h-4 mr-1" />
                    Export
                </div>
            </flux:button>
        </div>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 dark:bg-blue-900/30 p-3">
                        <flux:icon.users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalContacts) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Contacts</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-3">
                        <flux:icon.currency-dollar class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">RM {{ number_format($totalRevenue, 2) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Search and Filters -->
        <flux:card>
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search by name, email, student ID, or phone..."
                            icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="countryFilter" placeholder="All Countries">
                            <flux:select.option value="">All Countries</flux:select.option>
                            @foreach($countries as $country)
                                <flux:select.option value="{{ $country }}">{{ $country }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if($search || $countryFilter)
                        <flux:button wire:click="clearFilters" variant="ghost">
                            Clear Filters
                        </flux:button>
                    @endif
                </div>
            </div>

            <!-- Contacts Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Classes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Country/Region</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Revenue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Purchased Products</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($students as $student)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('students.show', $student) }}" class="flex items-center group">
                                        <flux:avatar size="sm" class="mr-3">
                                            {{ $student->user->initials() }}
                                        </flux:avatar>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $student->user->name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $student->user->email }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    @if($student->activeClasses->count() > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($student->activeClasses->take(2) as $class)
                                                <flux:badge size="sm" color="purple">{{ Str::limit($class->title, 20) }}</flux:badge>
                                            @endforeach
                                            @if($student->activeClasses->count() > 2)
                                                <flux:badge size="sm" color="gray">+{{ $student->activeClasses->count() - 2 }}</flux:badge>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">No classes</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($student->country === 'Malaysia')
                                            <span class="mr-2">🇲🇾</span>
                                        @endif
                                        <div class="text-sm text-gray-900 dark:text-gray-200">{{ $student->country ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-green-600">
                                        RM {{ number_format($student->orders->sum('total_amount'), 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-200">{{ $student->phone ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-gray-200">
                                        @php
                                            $products = $student->orders
                                                ->flatMap(fn($order) => $order->items)
                                                ->map(fn($item) => $item->product?->name ?? $item->product_name)
                                                ->filter()
                                                ->unique()
                                                ->take(3);
                                            $totalProducts = $student->orders
                                                ->flatMap(fn($order) => $order->items)
                                                ->map(fn($item) => $item->product?->name ?? $item->product_name)
                                                ->filter()
                                                ->unique()
                                                ->count();
                                        @endphp

                                        @if($products->count() > 0)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($products as $productName)
                                                    <flux:badge size="sm" color="blue">{{ Str::limit($productName, 20) }}</flux:badge>
                                                @endforeach
                                                @if($totalProducts > 3)
                                                    <flux:badge size="sm" color="gray">+{{ $totalProducts - 3 }}</flux:badge>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">No purchases</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <flux:dropdown position="bottom end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item href="{{ route('students.show', $student) }}" icon="eye">
                                                View Details
                                            </flux:menu.item>
                                            <flux:menu.item href="{{ route('students.edit', $student) }}" icon="pencil">
                                                Edit Student
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item href="{{ route('admin.students.payment-methods', $student) }}" icon="credit-card">
                                                Payment Methods
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <flux:icon.users class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No contacts found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        @if($search || $countryFilter)
                                            Try adjusting your search or filter criteria.
                                        @else
                                            No student contacts available in the database.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($students->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                    {{ $students->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
