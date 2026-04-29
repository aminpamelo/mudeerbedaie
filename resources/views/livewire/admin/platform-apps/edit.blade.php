<?php

use App\Models\Platform;
use App\Models\PlatformApp;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component
{
    public Platform $platform;

    public ?PlatformApp $app = null;

    #[Validate('required|string|max:255')]
    public string $slug = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string')]
    public string $category = PlatformApp::CATEGORY_MULTI_CHANNEL;

    #[Validate('required|string|max:255')]
    public string $app_key = '';

    public string $app_secret = '';

    public ?string $redirect_uri = null;

    public bool $is_active = true;

    public function mount(Platform $platform, ?PlatformApp $app = null): void
    {
        $this->platform = $platform;
        $this->app = $app?->exists ? $app : null;

        if ($this->app) {
            $this->slug = $this->app->slug;
            $this->name = $this->app->name;
            $this->category = $this->app->category;
            $this->app_key = $this->app->app_key;
            $this->redirect_uri = $this->app->redirect_uri;
            $this->is_active = $this->app->is_active;
        }
    }

    public function save(): void
    {
        $this->validate();

        if ($this->app) {
            $this->app->fill([
                'slug' => $this->slug,
                'name' => $this->name,
                'category' => $this->category,
                'app_key' => $this->app_key,
                'redirect_uri' => $this->redirect_uri ?: null,
                'is_active' => $this->is_active,
            ]);
            if (filled($this->app_secret)) {
                $this->app->setAppSecret($this->app_secret);
            }
            $this->app->save();
        } else {
            $this->validate(['app_secret' => 'required|string']);
            $newApp = new PlatformApp([
                'platform_id' => $this->platform->id,
                'slug' => $this->slug,
                'name' => $this->name,
                'category' => $this->category,
                'app_key' => $this->app_key,
                'redirect_uri' => $this->redirect_uri ?: null,
                'is_active' => $this->is_active,
            ]);
            $newApp->setAppSecret($this->app_secret);
            $newApp->save();
        }

        session()->flash('success', 'App saved.');
        $this->redirectRoute('platforms.apps.index', $this->platform);
    }
}; ?>

<div class="p-6 max-w-2xl">
    <flux:heading size="xl">{{ $app ? 'Edit App' : 'Register App' }}</flux:heading>

    <form wire:submit="save" class="mt-6 space-y-4">
        <flux:input wire:model="slug" label="Slug" placeholder="tiktok-analytics-reporting" />
        <flux:input wire:model="name" label="Name" />

        <flux:select wire:model="category" label="Category">
            <option value="multi_channel">Multi-Channel Management</option>
            <option value="analytics_reporting">Analytics & Reporting</option>
            <option value="affiliate">Affiliate</option>
            <option value="customer_service">Customer Service</option>
        </flux:select>

        <flux:input wire:model="app_key" label="App Key" />
        <flux:input
            wire:model="app_secret"
            label="App Secret"
            type="password"
            :placeholder="$app ? '(leave empty to keep current)' : 'Required'"
        />
        <flux:input wire:model="redirect_uri" label="Redirect URI (optional override)" />

        <flux:switch wire:model="is_active" label="Active" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Save</flux:button>
            <flux:button :href="route('platforms.apps.index', $platform)" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</div>
