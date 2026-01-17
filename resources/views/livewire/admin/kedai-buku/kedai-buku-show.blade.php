<?php

use App\Models\Agent;
use Livewire\Volt\Component;

new class extends Component {
    public Agent $kedaiBuku;

    public function mount(Agent $kedaiBuku): void
    {
        if (! $kedaiBuku->isBookstore()) {
            abort(404, 'Kedai Buku not found.');
        }

        $this->kedaiBuku = $kedaiBuku->load(['orders' => function ($query) {
            $query->with('items')->latest()->take(10);
        }, 'customPricing.product']);
    }

    public function toggleStatus(): void
    {
        $this->kedaiBuku->update(['is_active' => ! $this->kedaiBuku->is_active]);
        $this->kedaiBuku->refresh();

        session()->flash('success', 'Kedai Buku status updated successfully.');
    }

    public function delete(): void
    {
        if ($this->kedaiBuku->orders()->count() > 0) {
            session()->flash('error', 'Cannot delete kedai buku with existing orders.');
            return;
        }

        $this->kedaiBuku->delete();

        session()->flash('success', 'Kedai Buku deleted successfully.');

        $this->redirect(route('agents-kedai-buku.index'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button href="{{ route('agents-kedai-buku.index') }}" variant="outline" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to Kedai Buku
                </div>
            </flux:button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $kedaiBuku->name }}</flux:heading>
                <flux:text class="mt-2">{{ $kedaiBuku->agent_code }} - {{ ucfirst($kedaiBuku->pricing_tier ?? 'standard') }} Tier</flux:text>
            </div>
            <div class="flex items-center gap-3">
                <flux:badge :variant="$kedaiBuku->is_active ? 'success' : 'gray'" size="lg">
                    {{ $kedaiBuku->is_active ? 'Active' : 'Inactive' }}
                </flux:badge>
                <flux:button href="{{ route('agents-kedai-buku.edit', $kedaiBuku) }}" variant="primary" icon="pencil">
                    Edit
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="shopping-bag" class="h-8 w-8 text-blue-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Total Orders</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->total_orders }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="clock" class="h-8 w-8 text-yellow-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Pending Orders</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->pending_orders_count }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="banknotes" class="h-8 w-8 text-green-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Total Revenue</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-zinc-100">RM {{ number_format($kedaiBuku->total_revenue, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-circle" class="h-8 w-8 {{ $kedaiBuku->outstanding_balance > 0 ? 'text-amber-500' : 'text-gray-400' }}" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Outstanding</p>
                    <p class="text-2xl font-semibold {{ $kedaiBuku->outstanding_balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-zinc-100' }}">RM {{ number_format($kedaiBuku->outstanding_balance, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Basic Information</h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Bookstore Code</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->agent_code }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Name</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->name }}</flux:text>
                    </div>

                    @if($kedaiBuku->company_name)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Company Name</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->company_name }}</flux:text>
                        </div>
                    @endif

                    @if($kedaiBuku->registration_number)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Registration Number</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->registration_number }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Contact Information</h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if($kedaiBuku->contact_person)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Contact Person</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->contact_person }}</flux:text>
                        </div>
                    @endif

                    @if($kedaiBuku->phone)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Phone</flux:text>
                            <a href="tel:{{ $kedaiBuku->phone }}" class="mt-1 text-blue-600 dark:text-blue-400 hover:underline block">{{ $kedaiBuku->phone }}</a>
                        </div>
                    @endif

                    @if($kedaiBuku->email)
                        <div class="md:col-span-2">
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Email</flux:text>
                            <a href="mailto:{{ $kedaiBuku->email }}" class="mt-1 text-blue-600 dark:text-blue-400 hover:underline block">{{ $kedaiBuku->email }}</a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Address -->
            @if($kedaiBuku->address)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Address</h3>

                    <flux:text class="text-gray-900 dark:text-zinc-100 whitespace-pre-line">{{ $kedaiBuku->formatted_address }}</flux:text>
                </div>
            @endif

            <!-- Bank Details -->
            @if($kedaiBuku->bank_details)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Bank Details</h3>

                    <div class="space-y-3">
                        @if(isset($kedaiBuku->bank_details['bank_name']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Bank Name</flux:text>
                                <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->bank_details['bank_name'] }}</flux:text>
                            </div>
                        @endif

                        @if(isset($kedaiBuku->bank_details['account_number']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Account Number</flux:text>
                                <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->bank_details['account_number'] }}</flux:text>
                            </div>
                        @endif

                        @if(isset($kedaiBuku->bank_details['account_name']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Account Name</flux:text>
                                <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->bank_details['account_name'] }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Recent Orders -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100">Recent Orders</h3>
                    <flux:button href="{{ route('agents-kedai-buku.orders', $kedaiBuku) }}" variant="outline" size="sm">
                        View All Orders
                    </flux:button>
                </div>

                @if($kedaiBuku->orders->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-zinc-900">
                                <tr>
                                    <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400">Order #</th>
                                    <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400">Date</th>
                                    <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400">Items</th>
                                    <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400">Total</th>
                                    <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                                @foreach($kedaiBuku->orders as $order)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                                        <td class="py-2 px-3 text-sm">
                                            <a href="{{ route('agents-kedai-buku.orders.show', $order) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                {{ $order->order_number }}
                                            </a>
                                        </td>
                                        <td class="py-2 px-3 text-sm text-gray-500 dark:text-zinc-400">
                                            {{ $order->created_at->format('d M Y') }}
                                        </td>
                                        <td class="py-2 px-3 text-sm text-gray-900 dark:text-zinc-100">
                                            {{ $order->items->count() }}
                                        </td>
                                        <td class="py-2 px-3 text-sm font-medium text-gray-900 dark:text-zinc-100">
                                            RM {{ number_format($order->total_amount, 2) }}
                                        </td>
                                        <td class="py-2 px-3 text-sm">
                                            @php
                                                $statusVariant = match($order->status) {
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'delivered' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'outline',
                                                };
                                            @endphp
                                            <flux:badge :variant="$statusVariant" size="sm">
                                                {{ ucfirst($order->status) }}
                                            </flux:badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon name="shopping-bag" class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" />
                        <flux:text class="mt-2 text-gray-500 dark:text-zinc-400">No orders yet</flux:text>
                        <div class="mt-4">
                            <flux:button href="{{ route('agents-kedai-buku.orders.create', ['agent_id' => $kedaiBuku->id]) }}" variant="primary" size="sm" icon="plus">
                                Create Order
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Business Terms -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Business Terms</h3>

                <div class="space-y-4">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Pricing Tier</flux:text>
                        <div class="mt-1">
                            @php
                                $tierVariant = match($kedaiBuku->pricing_tier) {
                                    'vip' => 'warning',
                                    'premium' => 'info',
                                    default => 'outline',
                                };
                            @endphp
                            <flux:badge :variant="$tierVariant">
                                {{ ucfirst($kedaiBuku->pricing_tier ?? 'standard') }} ({{ $kedaiBuku->getTierDiscountPercentage() }}% discount)
                            </flux:badge>
                        </div>
                    </div>

                    @if($kedaiBuku->commission_rate)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Commission Rate</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->commission_rate }}%</flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Credit Limit</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">RM {{ number_format($kedaiBuku->credit_limit, 2) }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Available Credit</flux:text>
                        <flux:text class="mt-1 {{ $kedaiBuku->available_credit <= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            RM {{ number_format($kedaiBuku->available_credit, 2) }}
                        </flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Payment Terms</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->payment_terms ?: 'Not specified' }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Consignment</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->consignment_enabled ? 'Enabled' : 'Disabled' }}</flux:text>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            @if($kedaiBuku->notes)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Notes</h3>

                    <flux:text class="text-gray-900 dark:text-zinc-100 whitespace-pre-wrap">{{ $kedaiBuku->notes }}</flux:text>
                </div>
            @endif

            <!-- Actions -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Actions</h3>

                <div class="flex flex-col gap-3">
                    <flux:button href="{{ route('agents-kedai-buku.orders.create', ['agent_id' => $kedaiBuku->id]) }}" variant="primary" class="w-full" icon="plus">
                        Create New Order
                    </flux:button>
                    <flux:button href="{{ route('agents-kedai-buku.pricing', $kedaiBuku) }}" variant="outline" class="w-full" icon="currency-dollar">
                        Manage Pricing
                    </flux:button>
                    <flux:button wire:click="toggleStatus" variant="outline" class="w-full">
                        {{ $kedaiBuku->is_active ? 'Deactivate' : 'Activate' }} Kedai Buku
                    </flux:button>

                    @if($kedaiBuku->orders->count() === 0)
                        <flux:button
                            wire:click="delete"
                            wire:confirm="Are you sure you want to delete this kedai buku? This action cannot be undone."
                            variant="outline"
                            class="w-full text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30"
                        >
                            Delete Kedai Buku
                        </flux:button>
                    @endif
                </div>
            </div>

            <!-- Metadata -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Information</h3>

                <div class="space-y-3">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Created</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->created_at->format('M d, Y h:i A') }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Last Updated</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->updated_at->format('M d, Y h:i A') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
