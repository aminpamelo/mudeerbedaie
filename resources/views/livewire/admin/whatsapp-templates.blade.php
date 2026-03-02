<?php

use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = '';

    public bool $showModal = false;
    public ?int $editingTemplateId = null;
    public bool $isMetaSynced = false;

    public bool $showDeleteModal = false;
    public ?int $deletingTemplateId = null;
    public string $deletingTemplateName = '';

    public bool $showPreviewModal = false;
    public ?int $previewTemplateId = null;

    // Form fields
    public string $name = '';
    public string $language = 'ms';
    public string $category = 'marketing';
    public string $status = 'PENDING';
    public array $components = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = WhatsAppTemplate::query()
            ->when($this->search, function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->categoryFilter, function ($q) {
                $q->where('category', $this->categoryFilter);
            })
            ->latest();

        $statusCounts = WhatsAppTemplate::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'templates' => $query->paginate(15),
            'statusCounts' => $statusCounts,
            'totalCount' => array_sum($statusCounts),
        ];
    }

    public function syncFromMeta(): void
    {
        try {
            $count = app(TemplateService::class)->syncFromMeta();
            session()->flash('success', "Synced {$count} templates from Meta.");
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editingTemplateId = null;
        $this->isMetaSynced = false;
        $this->showModal = true;
    }

    public function openEditModal(WhatsAppTemplate $template): void
    {
        $this->editingTemplateId = $template->id;
        $this->isMetaSynced = $template->meta_template_id !== null;
        $this->name = $template->name;
        $this->language = $template->language;
        $this->category = $template->category;
        $this->status = $template->status;
        $this->components = $template->components ?? [];
        $this->showModal = true;
    }

    public function openPreviewModal(WhatsAppTemplate $template): void
    {
        $this->previewTemplateId = $template->id;
        $this->showPreviewModal = true;
    }

    public function confirmDelete(WhatsAppTemplate $template): void
    {
        $this->deletingTemplateId = $template->id;
        $this->deletingTemplateName = $template->name;
        $this->showDeleteModal = true;
    }

    public function deleteConfirmed(): void
    {
        if ($this->deletingTemplateId) {
            WhatsAppTemplate::findOrFail($this->deletingTemplateId)->delete();
            session()->flash('success', 'Template deleted successfully.');
        }

        $this->showDeleteModal = false;
        $this->deletingTemplateId = null;
        $this->deletingTemplateName = '';
    }

    public function save(): void
    {
        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('whatsapp_templates')
                    ->where('language', $this->language)
                    ->ignore($this->editingTemplateId),
            ],
            'language' => ['required', Rule::in(['ms', 'en', 'ar', 'id', 'th', 'zh_CN', 'zh_TW'])],
            'category' => ['required', Rule::in(['marketing', 'utility', 'authentication'])],
            'status' => ['required', Rule::in(['APPROVED', 'PENDING', 'REJECTED', 'PAUSED'])],
            'components' => 'array',
            'components.*.type' => ['required', Rule::in(['HEADER', 'BODY', 'FOOTER', 'BUTTONS'])],
            'components.*.text' => 'nullable|string|max:1024',
        ], [
            'name.regex' => 'Template name must only contain lowercase letters, numbers, and underscores.',
        ]);

        $data = [
            'name' => $this->name,
            'language' => $this->language,
            'category' => $this->category,
            'status' => $this->status,
            'components' => $this->components,
        ];

        if ($this->editingTemplateId) {
            $template = WhatsAppTemplate::findOrFail($this->editingTemplateId);
            $template->update($data);
            session()->flash('success', 'Template updated successfully.');
        } else {
            WhatsAppTemplate::create($data);
            session()->flash('success', 'Template created successfully.');
        }

        $this->closeModal();
    }

    public function addComponent(): void
    {
        $this->components[] = ['type' => 'BODY', 'text' => ''];
    }

    public function removeComponent(int $index): void
    {
        unset($this->components[$index]);
        $this->components = array_values($this->components);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->language = 'ms';
        $this->category = 'marketing';
        $this->status = 'PENDING';
        $this->components = [];
        $this->editingTemplateId = null;
        $this->isMetaSynced = false;
        $this->resetValidation();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }
};

?>

<div>
    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">WhatsApp Templates</flux:heading>
            <flux:text class="mt-2">Manage your WhatsApp message templates</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button
                variant="ghost"
                icon="arrow-path"
                wire:click="syncFromMeta"
                wire:loading.attr="disabled"
                wire:target="syncFromMeta"
            >
                <span wire:loading.remove wire:target="syncFromMeta">Sync from Meta</span>
                <span wire:loading wire:target="syncFromMeta">Syncing...</span>
            </flux:button>
            <flux:button variant="primary" wire:click="openCreateModal" icon="plus">
                Add Template
            </flux:button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <flux:callout variant="success" class="mb-4" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    @if (session()->has('error'))
        <flux:callout variant="danger" class="mb-4" icon="exclamation-triangle">
            {{ session('error') }}
        </flux:callout>
    @endif

    <div class="space-y-4">
        {{-- Status Tabs --}}
        <div class="flex items-center gap-1 border-b border-gray-200 dark:border-zinc-700">
            <button
                wire:click="setStatusFilter('')"
                class="relative px-4 py-2.5 text-sm font-medium transition-colors {{ $statusFilter === '' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                All
                <flux:badge size="sm" color="zinc" class="ml-1">{{ $totalCount }}</flux:badge>
                @if($statusFilter === '')
                    <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-zinc-900 dark:bg-white rounded-full"></span>
                @endif
            </button>
            @foreach (['APPROVED' => 'lime', 'PENDING' => 'yellow', 'REJECTED' => 'red', 'PAUSED' => 'zinc'] as $status => $color)
                <button
                    wire:click="setStatusFilter('{{ $status }}')"
                    class="relative px-4 py-2.5 text-sm font-medium transition-colors {{ $statusFilter === $status ? 'text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
                >
                    {{ ucfirst(strtolower($status)) }}
                    @if(($statusCounts[$status] ?? 0) > 0)
                        <flux:badge size="sm" color="{{ $color }}" class="ml-1">{{ $statusCounts[$status] ?? 0 }}</flux:badge>
                    @endif
                    @if($statusFilter === $status)
                        <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-zinc-900 dark:bg-white rounded-full"></span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Search & Filters --}}
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search templates..."
                    icon="magnifying-glass"
                />
            </div>
            <flux:select wire:model.live="categoryFilter" class="w-48">
                <option value="">All Categories</option>
                <option value="marketing">Marketing</option>
                <option value="utility">Utility</option>
                <option value="authentication">Authentication</option>
            </flux:select>
            @if($search || $categoryFilter || $statusFilter)
                <flux:button variant="ghost" size="sm" wire:click="clearFilters" icon="x-mark">
                    Clear
                </flux:button>
            @endif
        </div>

        {{-- Templates Table --}}
        <div wire:loading.class="opacity-50" wire:target="setStatusFilter, search, categoryFilter">
            <flux:card class="!p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                                <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Template</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Category</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Source</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Last Synced</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-700/50">
                            @forelse ($templates as $template)
                                <tr wire:key="template-{{ $template->id }}" class="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    {{-- Template Name + Body Preview + Language + Component Count --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-start gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-zinc-900 dark:text-zinc-100 text-sm">{{ $template->name }}</span>
                                                    <flux:badge size="sm" color="zinc" class="font-mono text-[10px]">{{ strtoupper($template->language) }}</flux:badge>
                                                </div>
                                                @if($template->components)
                                                    @php
                                                        $bodyComponent = collect($template->components)->firstWhere('type', 'BODY');
                                                    @endphp
                                                    @if($bodyComponent && isset($bodyComponent['text']))
                                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm truncate mt-0.5">
                                                            {{ $bodyComponent['text'] }}
                                                        </p>
                                                    @endif
                                                    <div class="flex items-center gap-1.5 mt-1">
                                                        @foreach(collect($template->components)->pluck('type')->unique() as $compType)
                                                            <span class="inline-flex items-center text-[10px] text-zinc-400 dark:text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">
                                                                {{ strtolower($compType) }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Category --}}
                                    <td class="px-5 py-3.5">
                                        @php
                                            $categoryIcon = match($template->category) {
                                                'marketing' => 'megaphone',
                                                'utility' => 'wrench-screwdriver',
                                                'authentication' => 'shield-check',
                                                default => 'document-text',
                                            };
                                        @endphp
                                        <div class="flex items-center gap-1.5">
                                            <flux:icon name="{{ $categoryIcon }}" class="w-3.5 h-3.5 text-zinc-400" />
                                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ ucfirst($template->category) }}</span>
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-5 py-3.5">
                                        @php
                                            $statusColor = match($template->status) {
                                                'APPROVED' => 'lime',
                                                'PENDING' => 'yellow',
                                                'REJECTED' => 'red',
                                                'PAUSED' => 'zinc',
                                                default => 'zinc',
                                            };
                                        @endphp
                                        <flux:badge size="sm" color="{{ $statusColor }}">{{ ucfirst(strtolower($template->status)) }}</flux:badge>
                                    </td>

                                    {{-- Source --}}
                                    <td class="px-5 py-3.5">
                                        @if($template->meta_template_id)
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-1.5 h-1.5 rounded-full bg-sky-500"></div>
                                                <span class="text-sm text-zinc-600 dark:text-zinc-400">Meta</span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-1.5 h-1.5 rounded-full bg-zinc-400"></div>
                                                <span class="text-sm text-zinc-500 dark:text-zinc-500">Local</span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Last Synced --}}
                                    <td class="px-5 py-3.5">
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $template->last_synced_at?->diffForHumans() ?? '-' }}
                                        </span>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-5 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="eye"
                                                wire:click="openPreviewModal({{ $template->id }})"
                                            >
                                                <flux:tooltip content="Preview" />
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil-square"
                                                wire:click="openEditModal({{ $template->id }})"
                                            >
                                                <flux:tooltip content="Edit" />
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="trash"
                                                class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                wire:click="confirmDelete({{ $template->id }})"
                                            >
                                                <flux:tooltip content="Delete" />
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-16 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-3">
                                                <flux:icon name="chat-bubble-left-right" class="w-6 h-6 text-zinc-400" />
                                            </div>
                                            <flux:heading size="sm" class="mb-1">No templates found</flux:heading>
                                            <flux:text class="text-zinc-500 dark:text-zinc-400 mb-4 text-sm">
                                                @if($search || $categoryFilter || $statusFilter)
                                                    Try adjusting your filters or search term.
                                                @else
                                                    Get started by syncing from Meta or creating a template manually.
                                                @endif
                                            </flux:text>
                                            <div class="flex items-center gap-2">
                                                @if($search || $categoryFilter || $statusFilter)
                                                    <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">
                                                        Clear filters
                                                    </flux:button>
                                                @else
                                                    <flux:button wire:click="syncFromMeta" variant="ghost" size="sm" icon="arrow-path">
                                                        Sync from Meta
                                                    </flux:button>
                                                    <flux:button wire:click="openCreateModal" variant="primary" size="sm" icon="plus">
                                                        Create template
                                                    </flux:button>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($templates->hasPages())
                    <div class="px-5 py-3 border-t border-gray-200 dark:border-zinc-700">
                        {{ $templates->links() }}
                    </div>
                @endif
            </flux:card>
        </div>
    </div>

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model="showModal" class="max-w-2xl">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <flux:icon name="{{ $editingTemplateId ? 'pencil-square' : 'plus' }}" class="w-4.5 h-4.5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingTemplateId ? 'Edit Template' : 'New Template' }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">{{ $editingTemplateId ? 'Update template details and components' : 'Create a new WhatsApp message template' }}</flux:text>
                </div>
            </div>

            @if($isMetaSynced)
                <flux:callout variant="warning" class="mb-4" icon="exclamation-triangle">
                    This template is synced from Meta. Name and language cannot be changed.
                </flux:callout>
            @endif

            <form wire:submit="save" class="space-y-5">
                {{-- Basic Info --}}
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Name</flux:label>
                        <flux:input
                            wire:model="name"
                            placeholder="e.g. order_confirmation"
                            :disabled="$isMetaSynced"
                        />
                        <flux:description>Lowercase letters, numbers, and underscores only.</flux:description>
                        @error('name') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Language</flux:label>
                        <flux:select wire:model="language" :disabled="$isMetaSynced">
                            <option value="ms">Malay (ms)</option>
                            <option value="en">English (en)</option>
                            <option value="ar">Arabic (ar)</option>
                            <option value="id">Indonesian (id)</option>
                            <option value="th">Thai (th)</option>
                            <option value="zh_CN">Chinese Simplified</option>
                            <option value="zh_TW">Chinese Traditional</option>
                        </flux:select>
                        @error('language') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                    </flux:field>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Category</flux:label>
                        <flux:select wire:model="category">
                            <option value="marketing">Marketing</option>
                            <option value="utility">Utility</option>
                            <option value="authentication">Authentication</option>
                        </flux:select>
                        @error('category') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="status">
                            <option value="APPROVED">Approved</option>
                            <option value="PENDING">Pending</option>
                            <option value="REJECTED">Rejected</option>
                            <option value="PAUSED">Paused</option>
                        </flux:select>
                        @error('status') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                    </flux:field>
                </div>

                <flux:separator />

                {{-- Components Repeater --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <flux:label>Components</flux:label>
                            <flux:text class="text-xs text-zinc-500 mt-0.5">Define the sections of your template message</flux:text>
                        </div>
                        <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addComponent">
                            Add Component
                        </flux:button>
                    </div>

                    @if(count($components) === 0)
                        <div class="rounded-lg border-2 border-dashed border-zinc-200 dark:border-zinc-700 p-8 text-center">
                            <div class="w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-3">
                                <flux:icon name="squares-plus" class="w-5 h-5 text-zinc-400" />
                            </div>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mb-2">No components added yet</flux:text>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="addComponent">
                                Add your first component
                            </flux:button>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($components as $index => $component)
                                <div wire:key="component-{{ $index }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30 overflow-hidden">
                                    <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-200/50 dark:border-zinc-700/50 bg-white dark:bg-zinc-800/50">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium text-zinc-400 uppercase tracking-wide">{{ $index + 1 }}.</span>
                                            <flux:select wire:model="components.{{ $index }}.type" class="!w-32 !text-sm !py-1">
                                                <option value="HEADER">Header</option>
                                                <option value="BODY">Body</option>
                                                <option value="FOOTER">Footer</option>
                                                <option value="BUTTONS">Buttons</option>
                                            </flux:select>
                                        </div>
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            class="!text-red-400 hover:!text-red-600 hover:!bg-red-50 dark:hover:!bg-red-900/20"
                                            wire:click="removeComponent({{ $index }})"
                                        />
                                    </div>
                                    <div class="p-3">
                                        <flux:textarea
                                            wire:model="components.{{ $index }}.text"
                                            placeholder="Component text content... Use {{1}}, {{2}} for variables"
                                            rows="2"
                                            class="!bg-white dark:!bg-zinc-800 !text-sm"
                                        />
                                    </div>
                                    @error("components.{$index}.type") <div class="px-3 pb-2"><flux:text class="text-red-500 text-xs">{{ $message }}</flux:text></div> @enderror
                                    @error("components.{$index}.text") <div class="px-3 pb-2"><flux:text class="text-red-500 text-xs">{{ $message }}</flux:text></div> @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @error('components') <flux:text class="text-red-500 text-sm mt-1">{{ $message }}</flux:text> @enderror
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-2 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="closeModal">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingTemplateId ? 'Update Template' : 'Create Template' }}</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Preview Modal --}}
    <flux:modal wire:model="showPreviewModal" class="max-w-sm">
        @if($previewTemplateId)
            @php
                $previewTemplate = \App\Models\WhatsAppTemplate::find($previewTemplateId);
            @endphp
            @if($previewTemplate)
                <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Template Preview</flux:heading>
                        <flux:badge size="sm" color="{{ match($previewTemplate->status) { 'APPROVED' => 'lime', 'PENDING' => 'yellow', 'REJECTED' => 'red', default => 'zinc' } }}">
                            {{ ucfirst(strtolower($previewTemplate->status)) }}
                        </flux:badge>
                    </div>

                    {{-- WhatsApp-style message preview --}}
                    <div class="rounded-xl bg-[#e5ddd5] dark:bg-[#0b141a] p-4 min-h-[200px]">
                        <div class="max-w-[280px] ml-auto">
                            <div class="bg-[#dcf8c6] dark:bg-[#005c4b] rounded-lg rounded-tr-none p-3 shadow-sm">
                                @if($previewTemplate->components)
                                    @foreach($previewTemplate->components as $comp)
                                        @if($comp['type'] === 'HEADER' && !empty($comp['text']))
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">{{ $comp['text'] }}</p>
                                        @elseif($comp['type'] === 'BODY' && !empty($comp['text']))
                                            <p class="text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed">{{ $comp['text'] }}</p>
                                        @elseif($comp['type'] === 'FOOTER' && !empty($comp['text']))
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2">{{ $comp['text'] }}</p>
                                        @endif
                                    @endforeach
                                @else
                                    <p class="text-sm text-zinc-500 italic">No content</p>
                                @endif
                                <div class="flex items-center justify-end gap-1 mt-1">
                                    <span class="text-[10px] text-zinc-500 dark:text-zinc-400">{{ now()->format('H:i') }}</span>
                                    <svg class="w-3.5 h-3.5 text-blue-500" viewBox="0 0 16 15" fill="currentColor">
                                        <path d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.88a.32.32 0 0 1-.484.032l-.358-.325a.32.32 0 0 0-.484.032l-.378.48a.418.418 0 0 0 .036.54l1.32 1.267a.32.32 0 0 0 .484-.034l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.88a.32.32 0 0 1-.484.032L1.891 7.77a.366.366 0 0 0-.516.005l-.423.433a.364.364 0 0 0 .006.514l3.255 3.185a.32.32 0 0 0 .484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Template info --}}
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Name</span>
                            <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $previewTemplate->name }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Language</span>
                            <span class="text-zinc-700 dark:text-zinc-300">{{ strtoupper($previewTemplate->language) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Category</span>
                            <span class="text-zinc-700 dark:text-zinc-300">{{ ucfirst($previewTemplate->category) }}</span>
                        </div>
                        @if($previewTemplate->meta_template_id)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-500">Meta ID</span>
                                <span class="font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $previewTemplate->meta_template_id }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <flux:button variant="ghost" wire:click="$set('showPreviewModal', false)" size="sm">
                            Close
                        </flux:button>
                        <flux:button variant="primary" wire:click="openEditModal({{ $previewTemplate->id }})" size="sm">
                            Edit Template
                        </flux:button>
                    </div>
                </div>
            @endif
        @endif
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
            </div>
            <flux:heading size="lg" class="mb-2">Delete Template</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Are you sure you want to delete <strong class="text-zinc-700 dark:text-zinc-300">{{ $deletingTemplateName }}</strong>? This action cannot be undone.
            </flux:text>
            <div class="flex items-center justify-center gap-2 mt-5">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                    Cancel
                </flux:button>
                <flux:button variant="danger" wire:click="deleteConfirmed">
                    <span wire:loading.remove wire:target="deleteConfirmed">Delete</span>
                    <span wire:loading wire:target="deleteConfirmed">Deleting...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
