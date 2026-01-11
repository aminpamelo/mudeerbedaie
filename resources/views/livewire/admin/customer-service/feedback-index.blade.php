<?php

use App\Models\CustomerFeedback;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';
    public string $ratingFilter = '';

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getFeedbacks()
    {
        return CustomerFeedback::query()
            ->with(['order', 'customer', 'reviewedBy'])
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->ratingFilter, fn($q) => $q->where('rating', $this->ratingFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    public function getStatusCounts(): array
    {
        return [
            'all' => CustomerFeedback::count(),
            'pending' => CustomerFeedback::where('status', 'pending')->count(),
            'reviewed' => CustomerFeedback::where('status', 'reviewed')->count(),
            'responded' => CustomerFeedback::where('status', 'responded')->count(),
            'archived' => CustomerFeedback::where('status', 'archived')->count(),
        ];
    }

    public function getAverageRating(): float
    {
        return CustomerFeedback::getAverageRating();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'ratingFilter']);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Customer Feedback</flux:heading>
            <flux:text class="mt-2">Manage customer feedback, complaints, and suggestions</flux:text>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right">
                <div class="text-2xl font-bold text-yellow-500">
                    @php $avgRating = $this->getAverageRating(); @endphp
                    {{ number_format($avgRating, 1) }} ★
                </div>
                <flux:text size="sm">Average Rating</flux:text>
            </div>
        </div>
    </div>

    @php $counts = $this->getStatusCounts(); @endphp

    <!-- Status Tabs -->
    <div class="mb-6 bg-white rounded-lg border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex gap-4 px-6">
                <button wire:click="$set('statusFilter', '')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === '' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    All <flux:badge size="sm" class="ml-2">{{ $counts['all'] }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'pending')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'pending' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Pending <flux:badge size="sm" color="yellow" class="ml-2">{{ $counts['pending'] }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'reviewed')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'reviewed' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Reviewed <flux:badge size="sm" color="blue" class="ml-2">{{ $counts['reviewed'] }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'responded')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'responded' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Responded <flux:badge size="sm" color="green" class="ml-2">{{ $counts['responded'] }}</flux:badge>
                </button>
                <button wire:click="$set('statusFilter', 'archived')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'archived' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Archived <flux:badge size="sm" color="gray" class="ml-2">{{ $counts['archived'] }}</flux:badge>
                </button>
            </nav>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-lg border p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search feedback, orders, customers..." />
            </div>
            <div>
                <flux:select wire:model.live="typeFilter">
                    <option value="">All Types</option>
                    <option value="complaint">Complaint</option>
                    <option value="suggestion">Suggestion</option>
                    <option value="compliment">Compliment</option>
                    <option value="question">Question</option>
                    <option value="other">Other</option>
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="ratingFilter">
                    <option value="">All Ratings</option>
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </flux:select>
            </div>
        </div>
        @if($search || $typeFilter || $ratingFilter)
            <div class="mt-4 pt-4 border-t">
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">Clear all filters</flux:button>
            </div>
        @endif
    </div>

    <!-- Feedback Table -->
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Feedback</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->getFeedbacks() as $feedback)
                    <tr class="hover:bg-gray-50" wire:key="feedback-{{ $feedback->id }}">
                        <td class="px-6 py-4">
                            <div>
                                <span class="font-semibold text-gray-900">{{ $feedback->feedback_number }}</span>
                                <p class="text-sm text-gray-500 truncate max-w-xs">{{ Str::limit($feedback->subject, 40) }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-gray-900">{{ $feedback->getCustomerName() }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $typeStyles = [
                                    'complaint' => 'bg-red-100 text-red-800',
                                    'suggestion' => 'bg-blue-100 text-blue-800',
                                    'compliment' => 'bg-green-100 text-green-800',
                                    'question' => 'bg-purple-100 text-purple-800',
                                    'other' => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeStyles[$feedback->type] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $feedback->getTypeLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($feedback->rating)
                                <span class="text-yellow-500">{{ $feedback->getRatingStars() }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($feedback->order)
                                <a href="{{ route('admin.orders.show', $feedback->order) }}" class="text-cyan-600 hover:underline font-medium" wire:navigate>
                                    {{ $feedback->order->order_number }}
                                </a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusStyles = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'reviewed' => 'bg-blue-100 text-blue-800',
                                    'responded' => 'bg-green-100 text-green-800',
                                    'archived' => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusStyles[$feedback->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $feedback->getStatusLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-900">{{ $feedback->created_at->format('M j, Y') }}</span>
                            <p class="text-xs text-gray-500">{{ $feedback->created_at->diffForHumans() }}</p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <flux:button variant="ghost" size="sm" href="{{ route('admin.customer-service.feedback.show', $feedback) }}" wire:navigate>
                                <flux:icon name="eye" class="w-4 h-4" />
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <flux:icon name="chat-bubble-left-right" class="w-12 h-12 mx-auto text-gray-300 mb-4" />
                            <flux:text class="text-gray-500">No feedback found</flux:text>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->getFeedbacks()->hasPages())
            <div class="px-6 py-4 border-t bg-gray-50">
                {{ $this->getFeedbacks()->links() }}
            </div>
        @endif
    </div>
</div>
