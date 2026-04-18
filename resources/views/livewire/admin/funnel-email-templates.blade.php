<?php

use App\Models\FunnelEmailTemplate;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

new class extends Component
{
    use WithPagination;

    public bool $showEditModal = false;
    public bool $showPreviewModal = false;
    public ?FunnelEmailTemplate $editingTemplate = null;

    // Form fields
    public string $name = '';
    public string $slug = '';
    public string $subject = '';
    public string $content = '';
    public string $category = '';
    public bool $is_active = true;

    // Preview
    public string $previewSubject = '';
    public string $previewContent = '';
    public bool $previewIsVisual = false;

    // Search
    public string $search = '';

    public function getTemplatesProperty()
    {
        $query = FunnelEmailTemplate::query()
            ->orderBy('category')
            ->orderBy('name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('subject', 'like', "%{$this->search}%");
            });
        }

        return $query->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createTemplate(): void
    {
        $this->resetForm();
        $this->showEditModal = true;
    }

    public function editTemplate(FunnelEmailTemplate $template): void
    {
        $this->editingTemplate = $template;
        $this->name = $template->name;
        $this->slug = $template->slug;
        $this->subject = $template->subject ?? '';
        $this->content = $template->content ?? '';
        $this->category = $template->category ?? '';
        $this->is_active = $template->is_active;
        $this->showEditModal = true;
    }

    public function saveTemplate()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:funnel_email_templates,slug,' . ($this->editingTemplate?->id ?? ''),
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:50',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'subject' => $this->subject ?: null,
            'content' => $this->content ?: null,
            'category' => $this->category ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingTemplate) {
            $this->editingTemplate->update($data);
            $this->showEditModal = false;
            $this->resetForm();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Template updated successfully',
            ]);

            return null;
        }

        $template = FunnelEmailTemplate::create($data);

        $this->showEditModal = false;
        $this->resetForm();

        return $this->redirect(route('admin.funnel-email-templates.builder', $template), navigate: true);
    }

    public function toggleActive(FunnelEmailTemplate $template): void
    {
        $template->update(['is_active' => !$template->is_active]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $template->is_active ? 'Template activated' : 'Template deactivated',
        ]);
    }

    public function duplicateTemplate(FunnelEmailTemplate $template): void
    {
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->slug = Str::slug($newTemplate->name) . '-' . Str::random(4);
        $newTemplate->save();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Template duplicated successfully',
        ]);
    }

    public function deleteTemplate(FunnelEmailTemplate $template): void
    {
        $template->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Template deleted successfully',
        ]);
    }

    public function previewTemplate(FunnelEmailTemplate $template): void
    {
        $sampleData = [
            '{{contact.name}}' => 'Ahmad Amin',
            '{{contact.first_name}}' => 'Ahmad',
            '{{contact.email}}' => 'ahmad@example.com',
            '{{contact.phone}}' => '+60123456789',
            '{{order.number}}' => 'PO-20260307-ABC',
            '{{order.total}}' => 'RM 299.00',
            '{{order.date}}' => now()->format('d M Y'),
            '{{order.items_list}}' => '1x Product Name - RM 299.00',
            '{{payment.method}}' => 'Credit Card',
            '{{payment.status}}' => 'Paid',
            '{{funnel.name}}' => 'My Sales Funnel',
            '{{funnel.url}}' => 'https://example.com/funnel',
            '{{current_date}}' => now()->format('d M Y'),
            '{{current_time}}' => now()->format('g:i A'),
            '{{company_name}}' => config('app.name'),
            '{{company_email}}' => config('mail.from.address'),
        ];

        $this->previewSubject = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $template->subject ?? ''
        );

        $content = $template->getEffectiveContent();
        $this->previewContent = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $content
        );
        $this->previewIsVisual = $template->isVisualEditor() && $template->html_content;
        $this->showPreviewModal = true;
    }

    public function updatedName(): void
    {
        if (!$this->editingTemplate) {
            $this->slug = Str::slug($this->name);
        }
    }

    protected function resetForm(): void
    {
        $this->editingTemplate = null;
        $this->name = '';
        $this->slug = '';
        $this->subject = '';
        $this->content = '';
        $this->category = '';
        $this->is_active = true;
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Funnel Email Templates</flux:heading>
            <flux:text class="mt-2">Create and manage reusable email templates for funnel automations</flux:text>
        </div>
        <flux:button variant="primary" wire:click="createTemplate">
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                Create Template
            </div>
        </flux:button>
    </div>

    <!-- Placeholders Reference -->
    <div x-data="{ showPlaceholders: false }" class="mb-6">
        <button
            x-on:click="showPlaceholders = !showPlaceholders"
            class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
        >
            <svg x-bind:class="showPlaceholders ? 'rotate-90' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="font-medium">Available Placeholders</span>
            <span class="text-xs text-gray-400">(Click to copy)</span>
        </button>
        <div x-show="showPlaceholders" x-collapse class="mt-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach(App\Models\FunnelEmailTemplate::getGroupedPlaceholders() as $group => $items)
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ $group }}</h4>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($items as $tag => $description)
                                <span
                                    x-data
                                    x-on:click="navigator.clipboard.writeText('{{ $tag }}'); $dispatch('notify', { type: 'success', message: 'Copied!' })"
                                    class="inline-flex items-center px-2 py-1 bg-white border border-gray-200 rounded text-xs font-mono cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition-colors"
                                    title="{{ $description }}"
                                >
                                    {!! e($tag) !!}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search templates..." icon="magnifying-glass" />
    </div>

    <!-- Templates Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Editor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->templates as $template)
                    <tr wire:key="template-{{ $template->id }}">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $template->name }}</div>
                            <div class="text-xs text-gray-500 font-mono">{{ $template->slug }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($template->category)
                                <flux:badge size="sm">{{ ucfirst($template->category) }}</flux:badge>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($template->editor_type === 'visual')
                                <flux:badge color="purple" size="sm">Visual</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Text</flux:badge>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($template->is_active)
                                <flux:badge color="green" size="sm">Active</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="previewTemplate({{ $template->id }})" title="Preview">
                                    <flux:icon name="eye" class="w-4 h-4" />
                                </flux:button>
                                @if($template->editor_type === 'visual' || !$template->content)
                                    <a href="{{ route('admin.funnel-email-templates.builder', $template) }}" title="Visual Builder">
                                        <flux:button size="sm" variant="ghost">
                                            <flux:icon name="paint-brush" class="w-4 h-4" />
                                        </flux:button>
                                    </a>
                                @endif
                                <flux:button size="sm" variant="ghost" wire:click="editTemplate({{ $template->id }})" title="Edit">
                                    <flux:icon name="pencil" class="w-4 h-4" />
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="duplicateTemplate({{ $template->id }})" title="Duplicate">
                                    <flux:icon name="document-duplicate" class="w-4 h-4" />
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $template->id }})" title="{{ $template->is_active ? 'Deactivate' : 'Activate' }}">
                                    <flux:icon name="{{ $template->is_active ? 'eye-slash' : 'eye' }}" class="w-4 h-4" />
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <flux:icon name="envelope" class="w-8 h-8 text-gray-300 mx-auto mb-2" />
                            <flux:text class="text-gray-500">No email templates yet</flux:text>
                            <flux:text class="text-sm text-gray-400 mt-1">Create your first template to get started</flux:text>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->templates->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">
                {{ $this->templates->links() }}
            </div>
        @endif
    </div>

    <!-- Edit/Create Modal -->
    <flux:modal wire:model="showEditModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingTemplate ? 'Edit Template' : 'Create Template' }}</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model.live="name" placeholder="e.g. Purchase Confirmation" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Slug</flux:label>
                    <flux:input wire:model="slug" placeholder="auto-generated-slug" />
                    <flux:error name="slug" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model="category">
                        <option value="">No category</option>
                        @foreach(App\Models\FunnelEmailTemplate::getCategories() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:switch wire:model="is_active" label="Active" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Subject</flux:label>
                <flux:input wire:model="subject" placeholder="Order #@{{order.number}} confirmed" />
                <flux:error name="subject" />
            </flux:field>

            @if($editingTemplate)
                <flux:field>
                    <flux:label>Content (Text)</flux:label>
                    <flux:textarea wire:model="content" rows="8" placeholder="Hi @{{contact.first_name}},&#10;&#10;Thank you for your order #@{{order.number}}!&#10;&#10;Total: @{{order.total}}" />
                    <flux:error name="content" />
                    <flux:description>Plain-text fallback. Use the Visual Builder (paint-brush icon) to design the HTML email.</flux:description>
                </flux:field>
            @else
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3">
                    <div class="flex items-start gap-2">
                        <flux:icon name="paint-brush" class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <flux:text size="sm" class="text-blue-900 dark:text-blue-200">
                            After saving, you'll be taken straight to the <span class="font-medium">Visual Builder</span> to design your email.
                        </flux:text>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveTemplate">
                    {{ $editingTemplate ? 'Update Template' : 'Create & Open Builder' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Preview Modal -->
    <flux:modal wire:model="showPreviewModal" class="max-w-4xl w-full">
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Template Preview</flux:heading>
            </div>

            @if($previewSubject)
                <div>
                    <flux:label>Subject</flux:label>
                    <div class="mt-1.5 px-4 py-3 bg-gray-50 dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $previewSubject }}</div>
                </div>
            @endif

            <div>
                <flux:label>Content</flux:label>
                @if($previewIsVisual)
                    <div class="mt-1.5 border border-gray-200 dark:border-zinc-700 rounded-lg overflow-hidden bg-white">
                        <iframe
                            srcdoc="{{ $previewContent }}"
                            class="w-full border-0"
                            style="min-height: 500px; height: 65vh; max-height: 700px;"
                            sandbox=""
                        ></iframe>
                    </div>
                @else
                    <div class="mt-1.5 p-5 bg-gray-50 dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-auto" style="max-height: 65vh;">
                        <pre class="text-sm whitespace-pre-wrap font-mono text-gray-700 dark:text-gray-300 leading-relaxed">{{ $previewContent }}</pre>
                    </div>
                @endif
            </div>

            <div class="flex justify-end pt-1">
                <flux:button variant="ghost" wire:click="$set('showPreviewModal', false)">Close</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
