<?php

use App\Models\CustomerFeedback;
use Livewire\Volt\Component;

new class extends Component
{
    public CustomerFeedback $feedback;
    public string $adminResponse = '';
    public string $newStatus = '';

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function mount(CustomerFeedback $feedback): void
    {
        $this->feedback = $feedback->load(['order.items', 'customer', 'reviewedBy']);
        $this->adminResponse = $feedback->admin_response ?? '';
        $this->newStatus = $feedback->status;
    }

    public function saveResponse(): void
    {
        $this->validate(['adminResponse' => 'required|min:2']);

        $this->feedback->respond($this->adminResponse, auth()->user());
        $this->feedback->refresh();

        $this->dispatch('response-saved');
    }

    public function updateStatus(): void
    {
        if ($this->newStatus === 'reviewed') {
            $this->feedback->markAsReviewed(auth()->user());
        } elseif ($this->newStatus === 'archived') {
            $this->feedback->archive();
        } else {
            $this->feedback->update(['status' => $this->newStatus]);
        }

        $this->feedback->refresh();
    }

    public function togglePublic(): void
    {
        $this->feedback->togglePublic();
        $this->feedback->refresh();
    }
}; ?>

<div>
    @php
        $statusStyles = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'reviewed' => 'bg-blue-100 text-blue-800',
            'responded' => 'bg-green-100 text-green-800',
            'archived' => 'bg-gray-100 text-gray-700',
        ];
        $typeStyles = [
            'complaint' => 'bg-red-100 text-red-800',
            'suggestion' => 'bg-blue-100 text-blue-800',
            'compliment' => 'bg-green-100 text-green-800',
            'question' => 'bg-purple-100 text-purple-800',
            'other' => 'bg-gray-100 text-gray-700',
        ];
    @endphp

    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <flux:heading size="xl">{{ $feedback->feedback_number }}</flux:heading>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusStyles[$feedback->status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ $feedback->getStatusLabel() }}
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $typeStyles[$feedback->type] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ $feedback->getTypeLabel() }}
                </span>
            </div>
            <flux:text class="text-gray-600">{{ $feedback->subject }}</flux:text>
        </div>
        <flux:button variant="ghost" :href="route('admin.customer-service.feedback.index')" wire:navigate>
            <div class="flex items-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Feedback
            </div>
        </flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Customer Feedback -->
            <div class="bg-white rounded-lg border">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-cyan-100 rounded-full flex items-center justify-center">
                                <flux:icon name="user" class="w-5 h-5 text-cyan-600" />
                            </div>
                            <div>
                                <flux:text class="font-semibold">{{ $feedback->getCustomerName() }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $feedback->created_at->format('M j, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                        @if($feedback->rating)
                            <div class="text-right">
                                <div class="text-xl text-yellow-500">{{ $feedback->getRatingStars() }}</div>
                                <flux:text size="sm" class="text-gray-500">{{ $feedback->rating }}/5 stars</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="p-6">
                    <div class="prose prose-sm max-w-none">
                        {!! nl2br(e($feedback->message)) !!}
                    </div>
                </div>
            </div>

            <!-- Admin Response -->
            @if($feedback->admin_response)
                <div class="bg-white rounded-lg border">
                    <div class="px-6 py-4 border-b bg-green-50">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-200 rounded-full flex items-center justify-center">
                                <flux:icon name="shield-check" class="w-5 h-5 text-green-700" />
                            </div>
                            <div>
                                <flux:text class="font-semibold">{{ $feedback->reviewedBy?->name ?? 'Admin' }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">
                                    Responded {{ $feedback->responded_at?->format('M j, Y g:i A') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="prose prose-sm max-w-none">
                            {!! nl2br(e($feedback->admin_response)) !!}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Response Form -->
            @if($feedback->status !== 'archived')
                <div class="bg-white rounded-lg border">
                    <div class="px-6 py-4 border-b">
                        <flux:heading size="lg">{{ $feedback->admin_response ? 'Update Response' : 'Add Response' }}</flux:heading>
                    </div>
                    <form wire:submit="saveResponse" class="p-6">
                        <div class="mb-4">
                            <flux:textarea wire:model="adminResponse" rows="4" placeholder="Type your response to the customer..." />
                            @error('adminResponse') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary">
                                <div class="flex items-center">
                                    <flux:icon name="paper-airplane" class="w-4 h-4 mr-2" />
                                    {{ $feedback->admin_response ? 'Update Response' : 'Send Response' }}
                                </div>
                            </flux:button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Order Info -->
            @if($feedback->order)
                <div class="bg-white rounded-lg border">
                    <div class="px-6 py-4 border-b">
                        <flux:heading size="lg">Related Order</flux:heading>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Number</flux:text>
                            <a href="{{ route('admin.orders.show', $feedback->order) }}" class="text-cyan-600 hover:underline font-semibold" wire:navigate>
                                {{ $feedback->order->order_number }}
                            </a>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Total</flux:text>
                            <flux:text class="font-semibold">RM {{ number_format($feedback->order->total_amount, 2) }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Status</flux:text>
                            <flux:badge size="sm">{{ ucfirst($feedback->order->status) }}</flux:badge>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Date</flux:text>
                            <flux:text>{{ $feedback->order->created_at->format('M j, Y') }}</flux:text>
                        </div>
                        @if($feedback->order->items->count() > 0)
                            <div class="pt-4 border-t">
                                <flux:text size="sm" class="text-gray-500 mb-2">Items</flux:text>
                                @foreach($feedback->order->items->take(3) as $item)
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>{{ Str::limit($item->product_name, 25) }}</span>
                                        <span class="text-gray-500">x{{ $item->quantity_ordered }}</span>
                                    </div>
                                @endforeach
                                @if($feedback->order->items->count() > 3)
                                    <flux:text size="xs" class="text-gray-400">+{{ $feedback->order->items->count() - 3 }} more items</flux:text>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Customer Info -->
            <div class="bg-white rounded-lg border">
                <div class="px-6 py-4 border-b">
                    <flux:heading size="lg">Customer</flux:heading>
                </div>
                <div class="p-6">
                    @if($feedback->customer)
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
                                <flux:icon name="user" class="w-6 h-6 text-gray-500" />
                            </div>
                            <div>
                                <flux:text class="font-semibold">{{ $feedback->customer->name }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $feedback->customer->email }}</flux:text>
                            </div>
                        </div>
                        @if($feedback->customer->phone)
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <flux:icon name="phone" class="w-4 h-4" />
                                {{ $feedback->customer->phone }}
                            </div>
                        @endif
                    @else
                        <flux:text class="text-gray-500">No customer linked</flux:text>
                    @endif
                </div>
            </div>

            <!-- Feedback Management -->
            <div class="bg-white rounded-lg border">
                <div class="px-6 py-4 border-b">
                    <flux:heading size="lg">Manage Feedback</flux:heading>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Status -->
                    <div>
                        <flux:label>Status</flux:label>
                        <div class="flex gap-2">
                            <flux:select wire:model="newStatus" class="flex-1">
                                <option value="pending">Pending</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="responded">Responded</option>
                                <option value="archived">Archived</option>
                            </flux:select>
                            <flux:button wire:click="updateStatus" size="sm">Update</flux:button>
                        </div>
                    </div>

                    <!-- Public Toggle -->
                    <div class="pt-4 border-t">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:label>Public Display</flux:label>
                                <flux:text size="sm" class="text-gray-500">Show on public testimonials</flux:text>
                            </div>
                            <flux:switch wire:click="togglePublic" :checked="$feedback->is_public" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feedback Info -->
            <div class="bg-white rounded-lg border">
                <div class="px-6 py-4 border-b">
                    <flux:heading size="lg">Feedback Info</flux:heading>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-gray-500">Type</flux:text>
                        <flux:badge size="sm" color="{{ $feedback->getTypeColor() }}">{{ $feedback->getTypeLabel() }}</flux:badge>
                    </div>
                    @if($feedback->rating)
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-gray-500">Rating</flux:text>
                            <flux:text size="sm" class="text-yellow-500">{{ $feedback->getRatingStars() }}</flux:text>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-gray-500">Submitted</flux:text>
                        <flux:text size="sm">{{ $feedback->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                    @if($feedback->responded_at)
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-gray-500">Responded</flux:text>
                            <flux:text size="sm">{{ $feedback->responded_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                    @if($feedback->reviewedBy)
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-gray-500">Reviewed By</flux:text>
                            <flux:text size="sm">{{ $feedback->reviewedBy->name }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
