<?php

use App\Models\Ticket;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public Ticket $ticket;
    public string $replyMessage = '';
    public bool $isInternal = false;
    public string $newStatus = '';
    public string $newPriority = '';
    public ?int $assignTo = null;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function mount(Ticket $ticket): void
    {
        $this->ticket = $ticket->load(['order.items', 'customer', 'assignedTo', 'replies.user']);
        $this->newStatus = $ticket->status;
        $this->newPriority = $ticket->priority;
        $this->assignTo = $ticket->assigned_to;
    }

    public function addReply(): void
    {
        $this->validate(['replyMessage' => 'required|min:2']);

        $this->ticket->addReply($this->replyMessage, auth()->user(), $this->isInternal);
        $this->replyMessage = '';
        $this->isInternal = false;
        $this->ticket->refresh();

        $this->dispatch('reply-added');
    }

    public function updateStatus(): void
    {
        if ($this->newStatus === 'resolved') {
            $this->ticket->resolve();
        } elseif ($this->newStatus === 'closed') {
            $this->ticket->close();
        } elseif ($this->newStatus === 'open' && in_array($this->ticket->status, ['resolved', 'closed'])) {
            $this->ticket->reopen();
        } else {
            $this->ticket->update(['status' => $this->newStatus]);
        }

        $this->ticket->refresh();
    }

    public function updatePriority(): void
    {
        $this->ticket->update(['priority' => $this->newPriority]);
        $this->ticket->refresh();
    }

    public function updateAssignment(): void
    {
        $user = $this->assignTo ? User::find($this->assignTo) : null;
        $this->ticket->assignTo($user);
        $this->ticket->refresh();
    }

    public function getStaffMembers()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <flux:button variant="ghost" size="sm" :href="route('admin.customer-service.tickets.index')" wire:navigate>
                <div class="flex items-center">
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                    Back to Tickets
                </div>
            </flux:button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $ticket->ticket_number }}</flux:heading>
                    <flux:badge size="lg" color="{{ $ticket->getStatusColor() }}">{{ $ticket->getStatusLabel() }}</flux:badge>
                    <flux:badge size="lg" color="{{ $ticket->getPriorityColor() }}">{{ ucfirst($ticket->priority) }}</flux:badge>
                </div>
                <flux:text class="mt-2">{{ $ticket->subject }}</flux:text>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Original Description -->
            <div class="bg-white rounded-lg border">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-cyan-100 rounded-full flex items-center justify-center">
                                <flux:icon name="user" class="w-5 h-5 text-cyan-600" />
                            </div>
                            <div>
                                <flux:text class="font-semibold">{{ $ticket->getCustomerName() }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $ticket->created_at->format('M j, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                        <flux:badge size="sm">{{ $ticket->getCategoryLabel() }}</flux:badge>
                    </div>
                </div>
                <div class="p-6">
                    <div class="prose prose-sm max-w-none">
                        {!! nl2br(e($ticket->description)) !!}
                    </div>
                </div>
            </div>

            <!-- Replies -->
            <div class="bg-white rounded-lg border">
                <div class="px-6 py-4 border-b">
                    <flux:heading size="lg">Conversation</flux:heading>
                </div>
                <div class="divide-y">
                    @forelse($ticket->replies as $reply)
                        <div class="p-6 {{ $reply->is_internal ? 'bg-yellow-50' : '' }}" wire:key="reply-{{ $reply->id }}">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 {{ $reply->is_internal ? 'bg-yellow-200' : 'bg-gray-200' }}">
                                    <flux:icon name="user" class="w-5 h-5 {{ $reply->is_internal ? 'text-yellow-700' : 'text-gray-600' }}" />
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <flux:text class="font-semibold">{{ $reply->user?->name ?? 'System' }}</flux:text>
                                        @if($reply->is_internal)
                                            <flux:badge size="xs" color="yellow">Internal Note</flux:badge>
                                        @endif
                                        <flux:text size="sm" class="text-gray-500">{{ $reply->created_at->diffForHumans() }}</flux:text>
                                    </div>
                                    <div class="prose prose-sm max-w-none">
                                        {!! nl2br(e($reply->message)) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">
                            <flux:text>No replies yet</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Reply Form -->
            @if($ticket->status !== 'closed')
                <div class="bg-white rounded-lg border">
                    <div class="px-6 py-4 border-b">
                        <flux:heading size="lg">Add Reply</flux:heading>
                    </div>
                    <form wire:submit="addReply" class="p-6">
                        <div class="mb-4">
                            <flux:textarea wire:model="replyMessage" rows="4" placeholder="Type your reply..." />
                            @error('replyMessage') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2">
                                <flux:checkbox wire:model="isInternal" />
                                <flux:text size="sm">Internal note (not visible to customer)</flux:text>
                            </label>
                            <flux:button type="submit" variant="primary">
                                <div class="flex items-center">
                                    <flux:icon name="paper-airplane" class="w-4 h-4 mr-2" />
                                    Send Reply
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
            @if($ticket->order)
                <div class="bg-white rounded-lg border">
                    <div class="px-6 py-4 border-b">
                        <flux:heading size="lg">Order Details</flux:heading>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Number</flux:text>
                            <a href="{{ route('admin.orders.show', $ticket->order) }}" class="text-cyan-600 hover:underline font-semibold" wire:navigate>
                                {{ $ticket->order->order_number }}
                            </a>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Total</flux:text>
                            <flux:text class="font-semibold">RM {{ number_format($ticket->order->total_amount, 2) }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Status</flux:text>
                            <flux:badge size="sm">{{ ucfirst($ticket->order->status) }}</flux:badge>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Date</flux:text>
                            <flux:text>{{ $ticket->order->created_at->format('M j, Y') }}</flux:text>
                        </div>
                        @if($ticket->order->items->count() > 0)
                            <div class="pt-4 border-t">
                                <flux:text size="sm" class="text-gray-500 mb-2">Items</flux:text>
                                @foreach($ticket->order->items->take(3) as $item)
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>{{ Str::limit($item->product_name, 25) }}</span>
                                        <span class="text-gray-500">x{{ $item->quantity_ordered }}</span>
                                    </div>
                                @endforeach
                                @if($ticket->order->items->count() > 3)
                                    <flux:text size="xs" class="text-gray-400">+{{ $ticket->order->items->count() - 3 }} more items</flux:text>
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
                    @if($ticket->customer)
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
                                <flux:icon name="user" class="w-6 h-6 text-gray-500" />
                            </div>
                            <div>
                                <flux:text class="font-semibold">{{ $ticket->customer->name }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $ticket->customer->email }}</flux:text>
                            </div>
                        </div>
                        @if($ticket->customer->phone)
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <flux:icon name="phone" class="w-4 h-4" />
                                {{ $ticket->customer->phone }}
                            </div>
                        @endif
                    @else
                        <flux:text class="text-gray-500">No customer linked</flux:text>
                    @endif
                </div>
            </div>

            <!-- Ticket Management -->
            <div class="bg-white rounded-lg border">
                <div class="px-6 py-4 border-b">
                    <flux:heading size="lg">Manage Ticket</flux:heading>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Status -->
                    <div>
                        <flux:label>Status</flux:label>
                        <div class="flex gap-2">
                            <flux:select wire:model="newStatus" class="flex-1">
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="pending">Pending</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </flux:select>
                            <flux:button wire:click="updateStatus" size="sm">Update</flux:button>
                        </div>
                    </div>

                    <!-- Priority -->
                    <div>
                        <flux:label>Priority</flux:label>
                        <div class="flex gap-2">
                            <flux:select wire:model="newPriority" class="flex-1">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </flux:select>
                            <flux:button wire:click="updatePriority" size="sm">Update</flux:button>
                        </div>
                    </div>

                    <!-- Assignment -->
                    <div>
                        <flux:label>Assigned To</flux:label>
                        <div class="flex gap-2">
                            <flux:select wire:model="assignTo" class="flex-1">
                                <option value="">Unassigned</option>
                                @foreach($this->getStaffMembers() as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:button wire:click="updateAssignment" size="sm">Update</flux:button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket Info -->
            <div class="bg-white rounded-lg border">
                <div class="px-6 py-4 border-b">
                    <flux:heading size="lg">Ticket Info</flux:heading>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-gray-500">Created</flux:text>
                        <flux:text size="sm">{{ $ticket->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                    @if($ticket->resolved_at)
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-gray-500">Resolved</flux:text>
                            <flux:text size="sm">{{ $ticket->resolved_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                    @if($ticket->closed_at)
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-gray-500">Closed</flux:text>
                            <flux:text size="sm">{{ $ticket->closed_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-gray-500">Replies</flux:text>
                        <flux:text size="sm">{{ $ticket->replies->count() }}</flux:text>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
