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

<div class="space-y-6 p-6">
    {{-- Breadcrumb --}}
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-1 text-sm">
            <li>
                <flux:button variant="ghost" size="sm" :href="route('platforms.apps.index', $platform)" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                        Back to Apps
                    </div>
                </flux:button>
            </li>
        </ol>
    </nav>

    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ $app ? 'Edit App' : 'Register App' }}</flux:heading>
        <flux:text class="mt-2">
            @if ($app)
                Update credentials and settings for <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $app->name }}</span>.
            @else
                Add a new TikTok Partner Center app. Each app's category determines which API scopes it can grant.
            @endif
        </flux:text>
    </div>

    <form wire:submit="save" class="max-w-3xl space-y-6">
        {{-- Section: Identity --}}
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Identity</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">How this app is labelled and categorised in your system.</p>
            </div>
            <div class="space-y-5 px-6 py-5">
                <flux:input
                    wire:model="name"
                    label="Name"
                    placeholder="TikTok Analytics & Reporting"
                    description="Display name shown in the admin UI and on the OAuth flow."
                />

                <flux:input
                    wire:model="slug"
                    label="Slug"
                    placeholder="tiktok-analytics-reporting"
                    description="Used in URLs (e.g. /tiktok/connect?app=…). Lowercase letters, numbers, and dashes."
                />

                <flux:select wire:model="category" label="Category" description="The Partner Center category determines which scopes this app can grant.">
                    <option value="multi_channel">Multi-Channel Management — orders, products, fulfilment</option>
                    <option value="analytics_reporting">Analytics &amp; Reporting — shop and video performance</option>
                    <option value="affiliate">Affiliate — creator and affiliate management</option>
                    <option value="customer_service">Customer Service — messaging and returns</option>
                </flux:select>
            </div>
        </section>

        {{-- Section: Credentials --}}
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Credentials</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">From your Partner Center app settings. The app secret is encrypted at rest.</p>
            </div>
            <div class="space-y-5 px-6 py-5">
                <flux:input
                    wire:model="app_key"
                    label="App Key"
                    placeholder="6abcd1234efghi567jkl890"
                />

                <flux:input
                    wire:model="app_secret"
                    label="App Secret"
                    type="password"
                    :placeholder="$app ? '(leave empty to keep current)' : 'Required'"
                    :description="$app ? 'Leave blank to keep the existing secret. Enter a new value to rotate.' : 'Encrypted before storage. Visible only via OAuth requests.'"
                />

                <flux:input
                    wire:model="redirect_uri"
                    label="Redirect URI"
                    placeholder="https://your-domain.test/admin/tiktok/callback"
                    description="Optional. Leave blank to use the system default from .env."
                />
            </div>
        </section>

        {{-- Section: Status --}}
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Status</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Inactive apps cannot be used for OAuth or sync. Existing credentials remain stored.</p>
            </div>
            <div class="px-6 py-5">
                <flux:switch wire:model="is_active" label="Active" />
            </div>
        </section>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-2">
            <flux:button :href="route('platforms.apps.index', $platform)" variant="ghost">Cancel</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $app ? 'Save changes' : 'Register app' }}
            </flux:button>
        </div>
    </form>
</div>
