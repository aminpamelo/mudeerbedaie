<?php

use App\Models\Ticket;
use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $orderId = null;
    public ?int $customerId = null;
    public string $subject = '';
    public string $description = '';
    public string $category = 'inquiry';
    public string $priority = 'medium';
    public ?int $assignTo = null;

    public string $orderSearch = '';

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function mount(): void
    {
        if (request()->has('order_id')) {
            $this->selectOrder(request()->get('order_id'));
        }
    }

    public function getOrders()
    {
        $query = ProductOrder::with('customer')->orderBy('created_at', 'desc');

        if ($this->orderSearch) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', "%{$this->orderSearch}%")
                    ->orWhere('customer_name', 'like', "%{$this->orderSearch}%")
                    ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->orderSearch}%"));
            });
        }

        return $query->limit(10)->get();
    }

    public function selectOrder(int $orderId): void
    {
        $order = ProductOrder::find($orderId);
        if ($order) {
            $this->orderId = $orderId;
            $this->customerId = $order->customer_id;
            $this->orderSearch = '';
        }
    }

    public function clearOrder(): void
    {
        $this->orderId = null;
        $this->customerId = null;
    }

    public function getSelectedOrder()
    {
        return $this->orderId ? ProductOrder::with('customer')->find($this->orderId) : null;
    }

    public function getStaffMembers()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->validate([
            'subject' => 'required|min:5|max:255',
            'description' => 'required|min:10',
            'category' => 'required|in:refund,return,complaint,inquiry,other',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $ticket = Ticket::create([
            'ticket_number' => Ticket::generateTicketNumber(),
            'order_id' => $this->orderId,
            'customer_id' => $this->customerId,
            'subject' => $this->subject,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'assigned_to' => $this->assignTo,
            'status' => $this->assignTo ? 'in_progress' : 'open',
        ]);

        session()->flash('success', 'Ticket created successfully.');
        $this->redirect(route('admin.customer-service.tickets.show', $ticket), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <flux:button variant="ghost" size="sm" :href="route('admin.customer-service.tickets.index')" wire:navigate>
                <div class="flex items-center">
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                    Back to Tickets
                </div>
            </flux:button>
        </div>
        <flux:heading size="xl">Create New Ticket</flux:heading>
        <flux:text class="mt-2">Create a support ticket for customer service</flux:text>
    </div>

    <form wire:submit="create">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Selection -->
                <div class="bg-white rounded-lg border">
                    <div class="px-6 py-4 border-b">
                        <flux:heading size="lg">Link to Order</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Associate this ticket with an order</flux:text>
                    </div>
                    <div class="p-6">
                        @if($selectedOrder = $this->getSelectedOrder())
                            <div class="bg-gray-50 rounded-lg p-4 flex items-center justify-between">
                                <div>
                                    <flux:text class="font-semibold">{{ $selectedOrder->order_number }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500">
                                        {{ $selectedOrder->getCustomerName() }} - RM {{ number_format($selectedOrder->total_amount, 2) }}
                                    </flux:text>
                                </div>
                                <flux:button variant="ghost" size="sm" wire:click="clearOrder">
                                    <flux:icon name="x-mark" class="w-4 h-4" />
                                </flux:button>
                            </div>
                        @else
                            <div class="mb-4">
                                <flux:input wire:model.live.debounce.300ms="orderSearch" placeholder="Search by order number or customer..." />
                            </div>
                            <div class="max-h-60 overflow-y-auto border rounded-lg">
                                @forelse($this->getOrders() as $order)
                                    <button type="button" wire:click="selectOrder({{ $order->id }})"
                                        class="w-full px-4 py-3 text-left hover:bg-gray-50 border-b last:border-b-0">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <flux:text class="font-medium">{{ $order->order_number }}</flux:text>
                                                <flux:text size="sm" class="text-gray-500">{{ $order->getCustomerName() }}</flux:text>
                                            </div>
                                            <div class="text-right">
                                                <flux:text class="font-semibold">RM {{ number_format($order->total_amount, 2) }}</flux:text>
                                                <flux:text size="sm" class="text-gray-500">{{ $order->created_at->format('M j, Y') }}</flux:text>
                                            </div>
                                        </div>
                                    </button>
                                @empty
                                    <div class="px-4 py-8 text-center text-gray-500">No orders found</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Ticket Details -->
                <div class="bg-white rounded-lg border">
                    <div class="px-6 py-4 border-b">
                        <flux:heading size="lg">Ticket Details</flux:heading>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <flux:label for="subject">Subject *</flux:label>
                            <flux:input wire:model="subject" id="subject" placeholder="Brief description of the issue" />
                            @error('subject') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <flux:label for="description">Description *</flux:label>
                            <flux:textarea wire:model="description" id="description" rows="6" placeholder="Provide detailed information about the issue..." />
                            @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Category & Priority -->
                <div class="bg-white rounded-lg border">
                    <div class="px-6 py-4 border-b">
                        <flux:heading size="lg">Classification</flux:heading>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <flux:label for="category">Category *</flux:label>
                            <flux:select wire:model="category" id="category">
                                <option value="refund">Refund Request</option>
                                <option value="return">Return Request</option>
                                <option value="complaint">Complaint</option>
                                <option value="inquiry">Inquiry</option>
                                <option value="other">Other</option>
                            </flux:select>
                        </div>

                        <div>
                            <flux:label for="priority">Priority *</flux:label>
                            <flux:select wire:model="priority" id="priority">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </flux:select>
                        </div>

                        <div>
                            <flux:label for="assignTo">Assign To</flux:label>
                            <flux:select wire:model="assignTo" id="assignTo">
                                <option value="">Unassigned</option>
                                @foreach($this->getStaffMembers() as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-lg border p-6">
                    <flux:button type="submit" variant="primary" class="w-full mb-3">
                        <div class="flex items-center justify-center">
                            <flux:icon name="plus" class="w-4 h-4 mr-2" />
                            Create Ticket
                        </div>
                    </flux:button>
                    <flux:button variant="ghost" class="w-full" :href="route('admin.customer-service.tickets.index')" wire:navigate>
                        Cancel
                    </flux:button>
                </div>
            </div>
        </div>
    </form>
</div>
