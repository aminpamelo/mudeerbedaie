<?php

use App\Models\Ticket;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $categoryFilter = '';
    public string $priorityFilter = '';
    public string $assigneeFilter = '';

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getTickets()
    {
        return Ticket::query()
            ->with(['order', 'customer', 'assignedTo'])
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter, fn($q) => $q->where('category', $this->categoryFilter))
            ->when($this->priorityFilter, fn($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->assigneeFilter, fn($q) => $this->assigneeFilter === 'unassigned'
                ? $q->whereNull('assigned_to')
                : $q->where('assigned_to', $this->assigneeFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    public function getStaffMembers()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function getStatusCounts(): array
    {
        return [
            'all' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'pending' => Ticket::where('status', 'pending')->count(),
            'resolved' => Ticket::where('status', 'resolved')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'categoryFilter', 'priorityFilter', 'assigneeFilter']);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Support Tickets</flux:heading>
            <flux:text class="mt-2">Manage customer support tickets for orders</flux:text>
        </div>
        <flux:button variant="primary" :href="route('admin.customer-service.tickets.create')" wire:navigate>
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="w-4 h-4 mr-2" />
                New Ticket
            </div>
        </flux:button>
    </div>

    @php $counts = $this->getStatusCounts(); @endphp

    <!-- Status Tabs -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="border-b border-gray-200 dark:border-zinc-700">
            <nav class="flex gap-4 px-6">
                <button wire:click="$set('statusFilter', '')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === '' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    All <flux:badge size="sm" class="ml-2">{{ number_format($counts['all']) }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'open')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'open' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Open <flux:badge size="sm" color="yellow" class="ml-2">{{ number_format($counts['open']) }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'in_progress')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'in_progress' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    In Progress <flux:badge size="sm" color="blue" class="ml-2">{{ number_format($counts['in_progress']) }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'pending')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'pending' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Pending <flux:badge size="sm" color="orange" class="ml-2">{{ number_format($counts['pending']) }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'resolved')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'resolved' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Resolved <flux:badge size="sm" color="green" class="ml-2">{{ number_format($counts['resolved']) }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'closed')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'closed' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Closed <flux:badge size="sm" color="gray" class="ml-2">{{ number_format($counts['closed']) }}</flux:badge>
                </button>
            </nav>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search tickets, orders, customers..." />
            </div>
            <div>
                <flux:select wire:model.live="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="refund">Refund Request</option>
                    <option value="return">Return Request</option>
                    <option value="complaint">Complaint</option>
                    <option value="inquiry">Inquiry</option>
                    <option value="other">Other</option>
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="priorityFilter">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="assigneeFilter">
                    <option value="">All Assignees</option>
                    <option value="unassigned">Unassigned</option>
                    @foreach($this->getStaffMembers() as $staff)
                        <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>
        @if($search || $categoryFilter || $priorityFilter || $assigneeFilter)
            <div class="mt-4 pt-4 border-t">
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">Clear all filters</flux:button>
            </div>
        @endif
    </div>

    <!-- Tickets Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                @forelse($this->getTickets() as $ticket)
                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50" wire:key="ticket-{{ $ticket->id }}">
                        <td class="px-6 py-4">
                            <div>
                                <span class="font-semibold text-gray-900">{{ $ticket->ticket_number }}</span>
                                <p class="text-sm text-gray-500 truncate max-w-xs">{{ Str::limit($ticket->subject, 40) }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($ticket->order)
                                <a href="{{ route('admin.orders.show', $ticket->order) }}" class="text-cyan-600 hover:underline font-medium" wire:navigate>
                                    {{ $ticket->order->order_number }}
                                </a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-gray-900">{{ $ticket->getCustomerName() }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700">{{ $ticket->getCategoryLabel() }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $priorityStyles = [
                                    'low' => 'bg-gray-100 text-gray-700',
                                    'medium' => 'bg-blue-100 text-blue-800',
                                    'high' => 'bg-orange-100 text-orange-800',
                                    'urgent' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $priorityStyles[$ticket->priority] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($ticket->priority) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusStyles = [
                                    'open' => 'bg-yellow-100 text-yellow-800',
                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                    'pending' => 'bg-orange-100 text-orange-800',
                                    'resolved' => 'bg-green-100 text-green-800',
                                    'closed' => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusStyles[$ticket->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $ticket->getStatusLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700">{{ $ticket->assignedTo?->name ?? 'Unassigned' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-900">{{ $ticket->created_at->format('M j, Y') }}</span>
                            <p class="text-xs text-gray-500">{{ $ticket->created_at->diffForHumans() }}</p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <flux:button variant="ghost" size="sm" href="{{ route('admin.customer-service.tickets.show', $ticket) }}" wire:navigate>
                                <flux:icon name="eye" class="w-4 h-4" />
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center">
                            <flux:icon name="ticket" class="w-12 h-12 mx-auto text-gray-300 mb-4" />
                            <flux:text class="text-gray-500">No tickets found</flux:text>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->getTickets()->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-700/50">
                {{ $this->getTickets()->links() }}
            </div>
        @endif
    </div>
</div>
