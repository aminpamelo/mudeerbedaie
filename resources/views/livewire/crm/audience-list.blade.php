<?php

use App\Models\Audience;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';

    public function mount(): void
    {
        //
    }

    public function with(): array
    {
        $query = Audience::query()
            ->withCount('students')
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('description', 'like', '%' . $this->search . '%');
        }

        return [
            'audiences' => $query->paginate(10),
            'totalAudiences' => Audience::count(),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete($id): void
    {
        $audience = Audience::findOrFail($id);
        $audience->delete();

        $this->dispatch('audience-deleted');
    }

}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Audiences</flux:heading>
            <flux:text class="mt-2">Create and manage audience segments for targeted campaigns</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('crm.audiences.create') }}">
            Create Audience
        </flux:button>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Search -->
        <flux:card>
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search audiences..."
                            icon="magnifying-glass" />
                    </div>
                </div>
            </div>

            <!-- Audiences Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created On</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contacts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($audiences as $audience)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $audience->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $audience->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $audience->created_at->format('g:i a') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">{{ $audience->description ?: '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-blue-600">
                                        {{ $audience->students_count }} of {{ $audience->students_count }}
                                    </div>
                                    <div class="text-xs text-gray-500">Subscribed</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <flux:button size="sm" variant="ghost" href="{{ route('crm.audiences.edit', $audience) }}">
                                            Edit
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="delete({{ $audience->id }})" wire:confirm="Are you sure you want to delete this audience?">
                                            Delete
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <flux:icon.users class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No audiences found</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        @if($search)
                                            Try adjusting your search criteria.
                                        @else
                                            Get started by creating your first audience segment.
                                        @endif
                                    </p>
                                    @if(!$search)
                                        <div class="mt-6">
                                            <flux:button variant="primary" href="{{ route('crm.audiences.create') }}">
                                                Create Audience
                                            </flux:button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($audiences->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $audiences->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
