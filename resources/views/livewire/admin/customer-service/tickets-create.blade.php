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
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Create New Ticket</flux:heading>
            <flux:text class="mt-2">Create a support ticket for customer service</flux:text>
        </div>
        <flux:button variant="ghost" :href="route('admin.customer-service.tickets.index')" wire:navigate>
            <div class="flex items-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Tickets
            </div>
        </flux:button>
    </div>

    <form wire:submit="create">
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
            <!-- Order Selection Section -->
            <div class="px-6 py-5 border-b">
                <div class="flex items-center gap-2 mb-1">
                    <flux:icon name="shopping-bag" class="w-5 h-5 text-gray-400" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Link to Order</h3>
                    <span class="text-xs text-gray-500">(Optional)</span>
                </div>
                <p class="text-sm text-gray-500 ml-7">Associate this ticket with an existing order</p>
            </div>
            <div class="px-6 py-5 border-b bg-gray-50">
                @if($selectedOrder = $this->getSelectedOrder())
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-cyan-100 rounded-full flex items-center justify-center">
                                <flux:icon name="document-text" class="w-5 h-5 text-cyan-600" />
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $selectedOrder->order_number }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $selectedOrder->getCustomerName() }} &bull; RM {{ number_format($selectedOrder->total_amount, 2) }}
                                </p>
                            </div>
                        </div>
                        <flux:button variant="ghost" size="sm" wire:click="clearOrder">
                            <flux:icon name="x-mark" class="w-4 h-4" />
                        </flux:button>
                    </div>
                @else
                    <div class="space-y-3">
                        <flux:input wire:model.live.debounce.300ms="orderSearch" placeholder="Search by order number or customer name..." icon="magnifying-glass" />
                        <div class="max-h-48 overflow-y-auto bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg divide-y dark:divide-zinc-700">
                            @forelse($this->getOrders() as $order)
                                <button type="button" wire:click="selectOrder({{ $order->id }})"
                                    class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $order->order_number }}</p>
                                            <p class="text-sm text-gray-500">{{ $order->getCustomerName() }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">RM {{ number_format($order->total_amount, 2) }}</p>
                                            <p class="text-xs text-gray-500">{{ $order->created_at->format('M j, Y') }}</p>
                                        </div>
                                    </div>
                                </button>
                            @empty
                                <div class="px-4 py-6 text-center text-gray-500">
                                    <flux:icon name="inbox" class="w-8 h-8 mx-auto text-gray-300 mb-2" />
                                    <p class="text-sm">No orders found</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <!-- Ticket Details Section -->
            <div class="px-6 py-5 border-b">
                <div class="flex items-center gap-2 mb-1">
                    <flux:icon name="document-text" class="w-5 h-5 text-gray-400" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ticket Details</h3>
                </div>
                <p class="text-sm text-gray-500 ml-7">Describe the issue or request</p>
            </div>
            <div class="px-6 py-5 border-b space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <flux:label for="category" class="mb-1.5">Category *</flux:label>
                        <flux:select wire:model="category" id="category">
                            <option value="inquiry">Inquiry</option>
                            <option value="refund">Refund Request</option>
                            <option value="return">Return Request</option>
                            <option value="complaint">Complaint</option>
                            <option value="other">Other</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="priority" class="mb-1.5">Priority *</flux:label>
                        <flux:select wire:model="priority" id="priority">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="assignTo" class="mb-1.5">Assign To</flux:label>
                        <flux:select wire:model="assignTo" id="assignTo">
                            <option value="">Unassigned</option>
                            @foreach($this->getStaffMembers() as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <div>
                    <flux:label for="subject" class="mb-1.5">Subject *</flux:label>
                    <flux:input wire:model="subject" id="subject" placeholder="Brief description of the issue" />
                    @error('subject') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <flux:label for="description" class="mb-1.5">Description *</flux:label>
                    <flux:textarea wire:model="description" id="description" rows="5" placeholder="Provide detailed information about the issue..." />
                    @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Actions -->
            <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
                <flux:button variant="danger" :href="route('admin.customer-service.tickets.index')" wire:navigate>
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit">
                    Create Ticket
                </flux:button>
            </div>
        </div>
    </form>
</div>
