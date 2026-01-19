<?php

use App\Models\Agent;
use Livewire\Volt\Component;

new class extends Component {
    public Agent $agent;

    public function mount(Agent $agent): void
    {
        $this->agent = $agent->load('warehouses.stockLevels');
    }

    public function toggleStatus(): void
    {
        $this->agent->update(['is_active' => ! $this->agent->is_active]);
        $this->agent->refresh();

        session()->flash('success', 'Agent status updated successfully.');
    }

    public function delete(): void
    {
        if ($this->agent->warehouses()->count() > 0) {
            session()->flash('error', 'Cannot delete agent with existing warehouses. Remove warehouses first.');

            return;
        }

        $this->agent->delete();

        session()->flash('success', 'Agent deleted successfully.');

        $this->redirect(route('agents.index'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button href="{{ route('agents.index') }}" variant="outline" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to Agents
                </div>
            </flux:button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $agent->name }}</flux:heading>
                <flux:text class="mt-2">{{ $agent->agent_code }} • {{ ucfirst($agent->type) }}</flux:text>
            </div>
            <div class="flex items-center gap-3">
                <flux:badge :variant="$agent->is_active ? 'success' : 'gray'" size="lg">
                    {{ $agent->is_active ? 'Active' : 'Inactive' }}
                </flux:badge>
                <flux:button href="{{ route('agents.edit', $agent) }}" variant="primary" icon="pencil">
                    Edit Agent
                </flux:button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Basic Information</h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500">Agent Code</flux:text>
                        <flux:text class="mt-1 text-gray-900">{{ $agent->agent_code }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500">Type</flux:text>
                        <flux:text class="mt-1 text-gray-900">{{ ucfirst($agent->type) }}</flux:text>
                    </div>

                    <div class="md:col-span-2">
                        <flux:text class="text-sm font-medium text-gray-500">Name</flux:text>
                        <flux:text class="mt-1 text-gray-900">{{ $agent->name }}</flux:text>
                    </div>

                    @if($agent->company_name)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500">Company Name</flux:text>
                            <flux:text class="mt-1 text-gray-900">{{ $agent->company_name }}</flux:text>
                        </div>
                    @endif

                    @if($agent->registration_number)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500">Registration Number</flux:text>
                            <flux:text class="mt-1 text-gray-900">{{ $agent->registration_number }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Contact Information</h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if($agent->contact_person)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500">Contact Person</flux:text>
                            <flux:text class="mt-1 text-gray-900">{{ $agent->contact_person }}</flux:text>
                        </div>
                    @endif

                    @if($agent->phone)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500">Phone</flux:text>
                            <flux:text class="mt-1 text-gray-900">{{ $agent->phone }}</flux:text>
                        </div>
                    @endif

                    @if($agent->email)
                        <div class="md:col-span-2">
                            <flux:text class="text-sm font-medium text-gray-500">Email</flux:text>
                            <flux:text class="mt-1 text-gray-900">{{ $agent->email }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Address -->
            @if($agent->address)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Address</h3>

                    <flux:text class="text-gray-900">{{ $agent->formatted_address }}</flux:text>
                </div>
            @endif

            <!-- Bank Details -->
            @if($agent->bank_details)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Bank Details</h3>

                    <div class="space-y-3">
                        @if(isset($agent->bank_details['bank_name']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500">Bank Name</flux:text>
                                <flux:text class="mt-1 text-gray-900">{{ $agent->bank_details['bank_name'] }}</flux:text>
                            </div>
                        @endif

                        @if(isset($agent->bank_details['account_number']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500">Account Number</flux:text>
                                <flux:text class="mt-1 text-gray-900">{{ $agent->bank_details['account_number'] }}</flux:text>
                            </div>
                        @endif

                        @if(isset($agent->bank_details['account_name']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500">Account Name</flux:text>
                                <flux:text class="mt-1 text-gray-900">{{ $agent->bank_details['account_name'] }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Warehouses -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Warehouses ({{ $agent->warehouses->count() }})</h3>
                    <flux:button href="{{ route('warehouses.create') }}" variant="primary" size="sm" icon="plus">
                        Add Warehouse
                    </flux:button>
                </div>

                @if($agent->warehouses->count() > 0)
                    <div class="space-y-3">
                        @foreach($agent->warehouses as $warehouse)
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-zinc-700/50 rounded-lg border border-gray-200 dark:border-zinc-700">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="font-medium text-gray-900 dark:text-gray-100">{{ $warehouse->name }}</flux:text>
                                        <flux:badge :variant="$warehouse->is_active ? 'success' : 'gray'" size="sm">
                                            {{ $warehouse->is_active ? 'Active' : 'Inactive' }}
                                        </flux:badge>
                                    </div>
                                    <flux:text class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $warehouse->code }} • {{ $warehouse->stock_levels_count ?? 0 }} items in stock
                                    </flux:text>
                                </div>
                                <flux:button href="{{ route('warehouses.show', $warehouse) }}" variant="outline" size="sm" icon="eye">
                                    View
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon name="building-office" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                        <flux:text class="mt-2 text-gray-500 dark:text-gray-400">No warehouses assigned yet</flux:text>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Payment Terms -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Payment Terms</h3>

                <flux:text class="text-gray-900 dark:text-gray-100">
                    {{ $agent->payment_terms ?: 'Not specified' }}
                </flux:text>
            </div>

            <!-- Notes -->
            @if($agent->notes)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Notes</h3>

                    <flux:text class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $agent->notes }}</flux:text>
                </div>
            @endif

            <!-- Actions -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Actions</h3>

                <div class="flex flex-col gap-3">
                    <flux:button wire:click="toggleStatus" variant="outline" class="w-full">
                        {{ $agent->is_active ? 'Deactivate' : 'Activate' }} Agent
                    </flux:button>

                    @if($agent->warehouses->count() === 0)
                        <flux:button
                            wire:click="delete"
                            wire:confirm="Are you sure you want to delete this agent? This action cannot be undone."
                            variant="outline"
                            class="w-full text-red-600 border-red-200 hover:bg-red-50"
                        >
                            Delete Agent
                        </flux:button>
                    @endif
                </div>
            </div>

            <!-- Metadata -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Information</h3>

                <div class="space-y-3">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500">Created</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900">{{ $agent->created_at->format('M d, Y h:i A') }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500">Last Updated</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900">{{ $agent->updated_at->format('M d, Y h:i A') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
