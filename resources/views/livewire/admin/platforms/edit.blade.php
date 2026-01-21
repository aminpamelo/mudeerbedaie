<?php

use Livewire\Volt\Component;
use App\Models\Platform;

new class extends Component {
    public Platform $platform;

    public $name = '';
    public $display_name = '';
    public $slug = '';
    public $type = '';
    public $description = '';
    public $logo_url = '';
    public $website_url = '';
    public $documentation_url = '';
    public $support_url = '';
    public $color_primary = '#6b7280';
    public $color_secondary = '#9ca3af';
    public $is_active = true;
    public $sort_order = 0;
    public $features = '';
    public $api_available = false;
    public $manual_mode = true;

    public function mount(Platform $platform)
    {
        $this->platform = $platform;

        // Populate form fields
        $this->name = $platform->name;
        $this->display_name = $platform->display_name;
        $this->slug = $platform->slug;
        $this->type = $platform->type;
        $this->description = $platform->description;
        $this->logo_url = $platform->logo_url ?? '';
        $this->website_url = $platform->website_url ?? '';
        $this->documentation_url = $platform->documentation_url ?? '';
        $this->support_url = $platform->support_url ?? '';
        $this->color_primary = $platform->color_primary ?? '#6b7280';
        $this->color_secondary = $platform->color_secondary ?? '#9ca3af';
        $this->is_active = $platform->is_active;
        $this->sort_order = $platform->sort_order;
        $this->features = is_array($platform->features) ? implode(', ', $platform->features) : '';
        $this->api_available = $platform->settings['api_available'] ?? false;
        $this->manual_mode = $platform->settings['manual_mode'] ?? true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255|unique:platforms,name,' . $this->platform->id,
            'display_name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:platforms,slug,' . $this->platform->id,
            'type' => 'required|in:marketplace,social_media,custom',
            'description' => 'required|string|max:1000',
            'logo_url' => 'nullable|url|max:500',
            'website_url' => 'nullable|url|max:500',
            'documentation_url' => 'nullable|url|max:500',
            'support_url' => 'nullable|url|max:500',
            'color_primary' => 'required|string|max:7',
            'color_secondary' => 'required|string|max:7',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'features' => 'nullable|string|max:1000',
            'api_available' => 'boolean',
            'manual_mode' => 'boolean',
        ];
    }

    public function updatedName()
    {
        if (!$this->display_name) {
            $this->display_name = $this->name;
        }
        $this->slug = \Str::slug($this->name);
    }

    public function update()
    {
        $validated = $this->validate();

        // Parse features into array
        $featuresArray = [];
        if ($validated['features']) {
            $featuresArray = array_map('trim', explode(',', $validated['features']));
        }

        // Update platform
        $this->platform->update([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'slug' => $validated['slug'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'logo_url' => $validated['logo_url'] ?: null,
            'website_url' => $validated['website_url'] ?: null,
            'documentation_url' => $validated['documentation_url'] ?: null,
            'support_url' => $validated['support_url'] ?: null,
            'color_primary' => $validated['color_primary'],
            'color_secondary' => $validated['color_secondary'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'],
            'features' => $featuresArray,
            'settings' => [
                'api_available' => $validated['api_available'],
                'manual_mode' => $validated['manual_mode'],
                'auto_sync' => $this->platform->settings['auto_sync'] ?? false,
            ],
        ]);

        return redirect()->route('platforms.show', $this->platform)
            ->with('success', "Platform '{$this->platform->name}' has been updated successfully");
    }
}; ?>

<div>
    {{-- Breadcrumb Navigation --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <flux:button variant="ghost" size="sm" :href="route('platforms.index')" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                                Platforms
                            </div>
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <flux:button variant="ghost" size="sm" :href="route('platforms.show', $platform)" wire:navigate class="ml-4">
                            {{ $platform->display_name }}
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">Edit</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit {{ $platform->display_name }}</flux:heading>
            <flux:text class="mt-2">Update platform details and configuration</flux:text>
        </div>
    </div>

    {{-- Platform Preview Card --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="flex items-center space-x-4">
            @if($logo_url)
                <img src="{{ $logo_url }}" alt="{{ $name }}" class="w-12 h-12 rounded-lg" onerror="this.style.display='none'">
            @endif
            @if(!$logo_url || true)
                <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white text-xl font-bold"
                     style="background: {{ $color_primary }}">
                    {{ $name ? substr($name, 0, 1) : substr($platform->name, 0, 1) }}
                </div>
            @endif
            <div class="flex-1">
                <flux:heading size="sm">{{ $display_name ?: $platform->display_name }} - Edit Mode</flux:heading>
                <flux:text size="sm" class="text-zinc-600">{{ $type ? ucfirst(str_replace('_', ' ', $type)) : ucfirst(str_replace('_', ' ', $platform->type)) }} Platform</flux:text>
            </div>
        </div>
    </div>

    {{-- Edit Form --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-3">
            <form wire:submit="update" class="space-y-6">
                {{-- Basic Information --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Basic Information</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label>Platform Name *</flux:label>
                                <flux:input wire:model.live="name" placeholder="e.g., TikTok Shop" />
                                <flux:description>The internal name for this platform</flux:description>
                                <flux:error name="name" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Display Name *</flux:label>
                                <flux:input wire:model="display_name" placeholder="e.g., TikTok Shop" />
                                <flux:description>The name shown to users</flux:description>
                                <flux:error name="display_name" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Slug *</flux:label>
                                <flux:input wire:model="slug" placeholder="tiktok-shop" />
                                <flux:description>URL-friendly identifier</flux:description>
                                <flux:error name="slug" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Platform Type *</flux:label>
                                <flux:select wire:model="type">
                                    <flux:select.option value="">Select type...</flux:select.option>
                                    <flux:select.option value="marketplace">Marketplace</flux:select.option>
                                    <flux:select.option value="social_media">Social Media</flux:select.option>
                                    <flux:select.option value="custom">Custom</flux:select.option>
                                </flux:select>
                                <flux:error name="type" />
                            </flux:field>
                        </div>

                        <div class="md:col-span-2">
                            <flux:field>
                                <flux:label>Description *</flux:label>
                                <flux:textarea wire:model="description" placeholder="Describe this platform and its features..." rows="3" />
                                <flux:error name="description" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                {{-- Visual & Branding --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Visual & Branding</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label>Logo URL</flux:label>
                                <flux:input wire:model="logo_url" type="url" placeholder="https://example.com/logo.png" />
                                <flux:description>URL to the platform's logo</flux:description>
                                <flux:error name="logo_url" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Sort Order</flux:label>
                                <flux:input wire:model="sort_order" type="number" min="0" placeholder="0" />
                                <flux:description>Display order (lower numbers appear first)</flux:description>
                                <flux:error name="sort_order" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Primary Color</flux:label>
                                <flux:input wire:model="color_primary" type="color" />
                                <flux:description>Primary brand color</flux:description>
                                <flux:error name="color_primary" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Secondary Color</flux:label>
                                <flux:input wire:model="color_secondary" type="color" />
                                <flux:description>Secondary brand color</flux:description>
                                <flux:error name="color_secondary" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                {{-- External Links --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">External Links</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label>Website URL</flux:label>
                                <flux:input wire:model="website_url" type="url" placeholder="https://platform.com" />
                                <flux:description>Official website</flux:description>
                                <flux:error name="website_url" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Documentation URL</flux:label>
                                <flux:input wire:model="documentation_url" type="url" placeholder="https://docs.platform.com" />
                                <flux:description>API documentation link</flux:description>
                                <flux:error name="documentation_url" />
                            </flux:field>
                        </div>

                        <div class="md:col-span-2">
                            <flux:field>
                                <flux:label>Support URL</flux:label>
                                <flux:input wire:model="support_url" type="url" placeholder="https://support.platform.com" />
                                <flux:description>Customer support link</flux:description>
                                <flux:error name="support_url" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                {{-- Features & Settings --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Features & Settings</flux:heading>

                    <div class="space-y-6">
                        <div>
                            <flux:field>
                                <flux:label>Features</flux:label>
                                <flux:input wire:model="features" placeholder="manual import, order management, csv export" />
                                <flux:description>Comma-separated list of features</flux:description>
                                <flux:error name="features" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:field>
                                <flux:checkbox wire:model="is_active" />
                                <flux:label>Platform is active</flux:label>
                                <flux:description>Active platforms are available for use</flux:description>
                            </flux:field>

                            <flux:field>
                                <flux:checkbox wire:model="manual_mode" />
                                <flux:label>Manual mode enabled</flux:label>
                                <flux:description>Allow manual order/data entry</flux:description>
                            </flux:field>

                            <flux:field>
                                <flux:checkbox wire:model="api_available" />
                                <flux:label>API integration available</flux:label>
                                <flux:description>Platform supports API connections</flux:description>
                            </flux:field>
                        </div>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="flex items-center justify-between pt-6">
                    <flux:button variant="ghost" :href="route('platforms.show', $platform)" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                            Cancel
                        </div>
                    </flux:button>

                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <div class="flex items-center justify-center">
                            <flux:icon name="check" class="w-4 h-4 mr-1" wire:loading.remove />
                            <flux:icon name="loading" class="w-4 h-4 mr-1 animate-spin" wire:loading />
                            <span wire:loading.remove>Update Platform</span>
                            <span wire:loading>Updating...</span>
                        </div>
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Live Preview --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Live Preview</flux:heading>

                <div class="border rounded-lg p-4" style="background: linear-gradient(135deg, {{ $color_primary }}15 0%, {{ $color_secondary }}15 100%);">
                    <div class="flex items-center space-x-3">
                        @if($logo_url)
                            <img src="{{ $logo_url }}" alt="{{ $name }}" class="w-10 h-10 rounded-lg" onerror="this.style.display='none'">
                        @else
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-lg font-bold"
                                 style="background: {{ $color_primary }}">
                                {{ $name ? substr($name, 0, 1) : substr($platform->name, 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <flux:text class="font-medium">{{ $display_name ?: $platform->display_name }}</flux:text>
                            <flux:text size="xs" class="text-zinc-600">{{ $type ? ucfirst(str_replace('_', ' ', $type)) : ucfirst(str_replace('_', ' ', $platform->type)) }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Update History --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Platform Info</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Created</flux:text>
                        <flux:text size="sm">{{ $platform->created_at->format('M j, Y') }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Last Updated</flux:text>
                        <flux:text size="sm">{{ $platform->updated_at->diffForHumans() }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Total Accounts</flux:text>
                        <flux:text size="sm">{{ $platform->accounts()->count() }}</flux:text>
                    </div>

                    @if($platform->accounts()->count() > 0)
                        <div class="pt-2 border-t">
                            <flux:text size="xs" class="text-amber-600">
                                ⚠️ This platform has {{ $platform->accounts()->count() }} account(s). Changes may affect existing integrations.
                            </flux:text>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

                <div class="space-y-3">
                    <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.accounts.index', $platform)" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="user-group" class="w-4 h-4 mr-2" />
                            Manage Accounts
                        </div>
                    </flux:button>

                    @if($platform->accounts()->count() === 0)
                        <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.accounts.create', $platform)" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="plus" class="w-4 h-4 mr-2" />
                                Add First Account
                            </div>
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>