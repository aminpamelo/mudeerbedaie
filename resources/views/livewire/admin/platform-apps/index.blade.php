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
}; ?>

<div class="space-y-6 p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $platform->name }} Apps</flux:heading>
            <flux:text class="mt-2">Register Partner Center apps and their credentials. Each category (Multi-Channel, Analytics, etc.) typically requires a separate app.</flux:text>
        </div>
        <flux:button :href="route('platforms.apps.create', $platform)" variant="primary">
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                Register App
            </div>
        </flux:button>
    </div>

    <div class="bg-white rounded-lg border border-gray-200">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-700">Name</th>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-700">Category</th>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-700">App Key</th>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-700">Status</th>
                    <th class="text-right px-4 py-3 text-sm font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($apps as $app)
                    <tr wire:key="app-{{ $app->id }}">
                        <td class="px-4 py-3">{{ $app->name }}</td>
                        <td class="px-4 py-3"><flux:badge>{{ $app->category }}</flux:badge></td>
                        <td class="px-4 py-3 font-mono text-xs">{{ Str::limit($app->app_key, 16) }}</td>
                        <td class="px-4 py-3">
                            @if ($app->is_active)
                                <flux:badge color="green">Active</flux:badge>
                            @else
                                <flux:badge color="zinc">Inactive</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <flux:button :href="route('platforms.apps.edit', [$platform, $app])" size="sm" variant="outline">Edit</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500">No apps registered yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
