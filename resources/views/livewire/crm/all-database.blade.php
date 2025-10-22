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
            ->with(['user', 'paidOrders', 'orders'])
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

        // Calculate aggregated stats
        $totalRevenue = Student::query()
            ->join('product_orders', 'students.id', '=', 'product_orders.student_id')
            ->whereNotNull('product_orders.paid_time')
            ->sum('product_orders.total_amount');

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
                    <div class="rounded-md bg-blue-50 p-3">
                        <flux:icon.users class="h-6 w-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($totalContacts) }}</p>
                        <p class="text-sm text-gray-500">Total Contacts</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 p-3">
                        <flux:icon.currency-dollar class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">RM {{ number_format($totalRevenue, 2) }}</p>
                        <p class="text-sm text-gray-500">Total Revenue</p>
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
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created On</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country/Region</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchased Products</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($students as $student)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <flux:avatar size="sm" class="mr-3">
                                            {{ $student->user->initials() }}
                                        </flux:avatar>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $student->user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $student->user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $student->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $student->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($student->country === 'Malaysia')
                                            <span class="mr-2">🇲🇾</span>
                                        @endif
                                        <div class="text-sm text-gray-900">{{ $student->country ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-green-600">
                                        RM {{ number_format($student->paidOrders->sum('total_amount'), 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $student->phone ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        @php
                                            $products = $student->paidOrders
                                                ->flatMap(fn($order) => $order->items)
                                                ->pluck('product.title')
                                                ->unique()
                                                ->take(3);
                                        @endphp

                                        @if($products->count() > 0)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($products as $productTitle)
                                                    <flux:badge size="sm" class="badge-blue">{{ $productTitle }}</flux:badge>
                                                @endforeach
                                                @if($student->paidOrders->flatMap(fn($order) => $order->items)->pluck('product.title')->unique()->count() > 3)
                                                    <flux:badge size="sm" class="badge-gray">+{{ $student->paidOrders->flatMap(fn($order) => $order->items)->pluck('product.title')->unique()->count() - 3 }}</flux:badge>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400">No purchases</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <flux:icon.users class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No contacts found</h3>
                                    <p class="mt-1 text-sm text-gray-500">
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
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $students->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
