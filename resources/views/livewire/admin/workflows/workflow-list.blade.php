<?php

use App\Models\Workflow;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';

    public function with(): array
    {
        return [
            'workflows' => Workflow::query()
                ->with('creator:id,name')
                ->withCount(['enrollments', 'activeEnrollments'])
                ->when($this->search, fn($query) => $query->where('name', 'like', '%' . $this->search . '%'))
                ->when($this->statusFilter, fn($query) => $query->where('status', $this->statusFilter))
                ->when($this->typeFilter, fn($query) => $query->where('type', $this->typeFilter))
                ->latest('updated_at')
                ->paginate(15),
        ];
    }

    public function createWorkflow(): void
    {
        $workflow = Workflow::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'New Workflow',
            'type' => 'automation',
            'status' => 'draft',
            'trigger_type' => 'manual',
            'created_by' => auth()->id(),
        ]);

        $this->redirect(route('workflow-builder.edit', $workflow->uuid), navigate: true);
    }

    public function duplicate(Workflow $workflow): void
    {
        $newWorkflow = $workflow->replicate();
        $newWorkflow->uuid = (string) \Illuminate\Support\Str::uuid();
        $newWorkflow->name = $workflow->name . ' (Copy)';
        $newWorkflow->status = 'draft';
        $newWorkflow->created_by = auth()->id();
        $newWorkflow->save();

        // Duplicate steps and connections
        $stepMapping = [];
        foreach ($workflow->steps as $step) {
            $newStep = $step->replicate();
            $newStep->workflow_id = $newWorkflow->id;
            $newStep->uuid = (string) \Illuminate\Support\Str::uuid();
            $newStep->save();
            $stepMapping[$step->id] = $newStep->id;
        }

        foreach ($workflow->connections as $connection) {
            $newConnection = $connection->replicate();
            $newConnection->workflow_id = $newWorkflow->id;
            $newConnection->source_step_id = $stepMapping[$connection->source_step_id] ?? null;
            $newConnection->target_step_id = $stepMapping[$connection->target_step_id] ?? null;
            if ($newConnection->source_step_id && $newConnection->target_step_id) {
                $newConnection->save();
            }
        }

        session()->flash('success', 'Workflow duplicated successfully.');
    }

    public function toggleStatus(Workflow $workflow): void
    {
        if ($workflow->status === 'active') {
            $workflow->pause();
            session()->flash('success', 'Workflow paused successfully.');
        } elseif ($workflow->status === 'draft' || $workflow->status === 'paused') {
            // Validate before publishing
            $triggerSteps = $workflow->steps()->where('type', 'trigger')->count();
            $actionSteps = $workflow->steps()->where('type', 'action')->count();

            if ($triggerSteps === 0) {
                session()->flash('error', 'Workflow must have at least one trigger to publish.');
                return;
            }

            if ($actionSteps === 0) {
                session()->flash('error', 'Workflow must have at least one action to publish.');
                return;
            }

            $workflow->publish();
            session()->flash('success', 'Workflow published successfully.');
        }
    }

    public function delete(Workflow $workflow): void
    {
        if ($workflow->activeEnrollments()->exists()) {
            session()->flash('error', 'Cannot delete workflow with active enrollments.');
            return;
        }

        $workflow->delete();
        session()->flash('success', 'Workflow deleted successfully.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Automation Workflows</flux:heading>
            <flux:text class="mt-2">Create and manage automated workflows for your CRM</flux:text>
        </div>
        <flux:button variant="primary" wire:click="createWorkflow" icon="plus">
            Create Workflow
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search workflows..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
            <flux:select.option value="">All Statuses</flux:select.option>
            <flux:select.option value="draft">Draft</flux:select.option>
            <flux:select.option value="active">Active</flux:select.option>
            <flux:select.option value="paused">Paused</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="typeFilter" placeholder="All Types">
            <flux:select.option value="">All Types</flux:select.option>
            <flux:select.option value="automation">Automation</flux:select.option>
            <flux:select.option value="sequence">Sequence</flux:select.option>
            <flux:select.option value="broadcast">Broadcast</flux:select.option>
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Workflows Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-6">Workflow</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Type</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Trigger</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Enrollments</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Last Updated</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($workflows as $workflow)
                        <tr wire:key="workflow-{{ $workflow->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $workflow->name }}</div>
                                    @if($workflow->description)
                                        <div class="text-gray-500 dark:text-gray-400 text-xs truncate max-w-xs">{{ $workflow->description }}</div>
                                    @endif
                                    @if($workflow->creator)
                                        <div class="text-gray-400 dark:text-gray-500 text-xs">by {{ $workflow->creator->name }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <flux:badge variant="outline" size="sm">
                                    {{ ucfirst($workflow->type) }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                                <span class="text-gray-500 dark:text-gray-400">
                                    {{ str_replace('_', ' ', ucfirst($workflow->trigger_type ?? 'Manual')) }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <flux:badge variant="outline" size="sm">
                                        {{ $workflow->enrollments_count }} total
                                    </flux:badge>
                                    @if($workflow->active_enrollments_count > 0)
                                        <flux:badge variant="success" size="sm">
                                            {{ $workflow->active_enrollments_count }} active
                                        </flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                @php
                                    $statusColors = [
                                        'draft' => 'gray',
                                        'active' => 'success',
                                        'paused' => 'warning',
                                    ];
                                @endphp
                                <flux:badge :variant="$statusColors[$workflow->status] ?? 'outline'" size="sm">
                                    {{ ucfirst($workflow->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $workflow->updated_at->diffForHumans() }}
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button
                                        href="{{ route('workflow-builder.edit', $workflow->uuid) }}"
                                        variant="outline"
                                        size="sm"
                                        icon="pencil-square"
                                    >
                                        Edit
                                    </flux:button>
                                    <flux:button
                                        wire:click="toggleStatus({{ $workflow->id }})"
                                        variant="outline"
                                        size="sm"
                                        :icon="$workflow->status === 'active' ? 'pause' : 'play'"
                                    >
                                        {{ $workflow->status === 'active' ? 'Pause' : 'Publish' }}
                                    </flux:button>
                                    <flux:dropdown align="end">
                                        <flux:button variant="outline" size="sm" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="duplicate({{ $workflow->id }})" icon="document-duplicate">
                                                Duplicate
                                            </flux:menu.item>
                                            @if(!$workflow->activeEnrollments()->exists())
                                                <flux:menu.separator />
                                                <flux:menu.item
                                                    wire:click="delete({{ $workflow->id }})"
                                                    wire:confirm="Are you sure you want to delete this workflow?"
                                                    icon="trash"
                                                    variant="danger"
                                                >
                                                    Delete
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div>
                                    <flux:icon name="bolt" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No workflows found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first automation workflow.</p>
                                    <div class="mt-6">
                                        <flux:button variant="primary" wire:click="createWorkflow" icon="plus">
                                            Create Workflow
                                        </flux:button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $workflows->links() }}
    </div>
</div>
