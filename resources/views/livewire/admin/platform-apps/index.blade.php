<?php

use App\Models\Platform;
use App\Models\PlatformApp;
use Livewire\Volt\Component;

new class extends Component
{
    public Platform $platform;

    public function mount(Platform $platform): void
    {
        $this->platform = $platform;
    }

    public function with(): array
    {
        return [
            'apps' => PlatformApp::where('platform_id', $this->platform->id)
                ->orderBy('category')
                ->get(),
        ];
    }

    private array $categoryLabels = [
        'multi_channel' => 'Multi-Channel Management',
        'analytics_reporting' => 'Analytics & Reporting',
        'affiliate' => 'Affiliate',
        'customer_service' => 'Customer Service',
    ];

    public function categoryLabel(string $category): string
    {
        return $this->categoryLabels[$category] ?? $category;
    }
}; ?>

<div class="space-y-6 p-6">
    {{-- Breadcrumb --}}
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-1 text-sm">
            <li>
                <flux:button variant="ghost" size="sm" :href="route('platforms.index')" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                        Platforms
                    </div>
                </flux:button>
            </li>
            <li class="flex items-center">
                <flux:icon name="chevron-right" class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                <span class="ml-1 text-zinc-500 dark:text-zinc-400">{{ $platform->display_name ?? $platform->name }}</span>
            </li>
            <li class="flex items-center">
                <flux:icon name="chevron-right" class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                <span class="ml-1 font-medium text-zinc-900 dark:text-zinc-100">Apps</span>
            </li>
        </ol>
    </nav>

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <flux:heading size="xl">{{ $platform->name }} Apps</flux:heading>
            <flux:text class="mt-2 max-w-2xl">
                Register Partner Center apps and their credentials. Each TikTok Shop category
                (Multi-Channel, Analytics &amp; Reporting, etc.) typically requires its own app
                with a distinct scope set.
            </flux:text>
        </div>
        <flux:button :href="route('platforms.apps.create', $platform)" variant="primary" icon="plus">
            Register App
        </flux:button>
    </div>

    {{-- Apps table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        @if ($apps->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="key" class="h-6 w-6 text-zinc-500 dark:text-zinc-400" />
                </div>
                <h3 class="mt-4 text-base font-semibold text-zinc-900 dark:text-zinc-100">No apps registered yet</h3>
                <p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">
                    Register your first {{ $platform->name }} app to enable OAuth and sync flows.
                </p>
                <flux:button class="mt-4" :href="route('platforms.apps.create', $platform)" variant="primary" icon="plus">
                    Register App
                </flux:button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/60">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">App</th>
                            <th class="hidden px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 sm:table-cell">App Key</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($apps as $app)
                            <tr wire:key="app-{{ $app->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $app->name }}</div>
                                    <div class="mt-0.5 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>{{ $this->categoryLabel($app->category) }}</span>
                                        <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                        <code class="font-mono text-[11px]">{{ $app->slug }}</code>
                                    </div>
                                </td>
                                <td class="hidden px-6 py-4 sm:table-cell">
                                    <code class="font-mono text-xs text-zinc-700 dark:text-zinc-300">
                                        {{ Str::limit($app->app_key, 24) }}
                                    </code>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($app->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 ring-1 ring-inset ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span>
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <flux:button :href="route('platforms.apps.edit', [$platform, $app])" size="sm" variant="outline">
                                        Edit
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
