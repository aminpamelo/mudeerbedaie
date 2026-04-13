<?php

use App\Models\CustomDomain;
use App\Services\CloudflareCustomHostnameService;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';

    public function with(): array
    {
        return [
            'domains' => CustomDomain::query()
                ->with(['funnel:id,name,slug', 'user:id,name,email'])
                ->when($this->search, fn ($query) => $query->where('domain', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter, fn ($query) => $query->where('verification_status', $this->statusFilter))
                ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
                ->latest()
                ->paginate(15),
        ];
    }

    public function deleteDomain(CustomDomain $domain): void
    {
        if ($domain->type === 'custom' && $domain->cloudflare_hostname_id) {
            try {
                app(CloudflareCustomHostnameService::class)->deleteHostname($domain->cloudflare_hostname_id);
            } catch (\Exception $e) {
                // Log but continue with deletion
            }
        }

        $cacheKey = $domain->type === 'subdomain'
            ? "custom_domain:subdomain:{$domain->domain}"
            : "custom_domain:custom:{$domain->domain}";
        Cache::forget($cacheKey);

        $domain->delete();
        $this->dispatch('domain-deleted');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->typeFilter = '';
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Custom Domains</flux:heading>
            <flux:text class="mt-2">Manage custom domains and subdomains for your funnels</flux:text>
        </div>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Filters -->
        <flux:card>
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <flux:input wire:model.live="search" placeholder="Search domains..." icon="magnifying-glass" />
                    </div>
                    <div>
                        <flux:select wire:model.live="statusFilter">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="failed">Failed</option>
                            <option value="deleting">Deleting</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:select wire:model.live="typeFilter">
                            <option value="">All Types</option>
                            <option value="custom">Custom</option>
                            <option value="subdomain">Subdomain</option>
                        </flux:select>
                    </div>
                </div>
                @if($search || $statusFilter || $typeFilter)
                    <div class="mt-4">
                        <flux:button size="sm" variant="ghost" wire:click="clearFilters" icon="x-mark">
                            Clear Filters
                        </flux:button>
                    </div>
                @endif
            </div>

            <!-- Domains Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse border-0">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domain</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Funnel</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Owner</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verification</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">SSL</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800">
                        @forelse ($domains as $domain)
                            <tr wire:key="domain-{{ $domain->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $domain->domain }}</div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($domain->funnel)
                                        <a href="{{ route('admin.funnels.show', $domain->funnel) }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                            {{ $domain->funnel->name }}
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($domain->user)
                                        <div>
                                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $domain->user->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $domain->user->email }}</div>
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ $domain->type === 'custom' ? 'blue' : 'purple' }}">
                                        {{ ucfirst($domain->type) }}
                                    </flux:badge>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ match($domain->verification_status) {
                                        'active' => 'green',
                                        'pending' => 'yellow',
                                        'failed' => 'red',
                                        'deleting' => 'zinc',
                                        default => 'zinc'
                                    } }}">
                                        {{ ucfirst($domain->verification_status) }}
                                    </flux:badge>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($domain->ssl_status)
                                        <flux:badge size="sm" color="{{ match($domain->ssl_status) {
                                            'active' => 'green',
                                            'pending' => 'yellow',
                                            'failed' => 'red',
                                            default => 'zinc'
                                        } }}">
                                            {{ ucfirst($domain->ssl_status) }}
                                        </flux:badge>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $domain->created_at->format('M d, Y') }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                                        <flux:menu>
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="deleteDomain({{ $domain->id }})"
                                                wire:confirm="Are you sure you want to delete this domain? This action cannot be undone."
                                            >
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <flux:icon name="globe-alt" class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" />
                                        <flux:heading size="lg" class="text-gray-500 dark:text-gray-400">No custom domains found</flux:heading>
                                        <flux:text class="text-gray-400 dark:text-gray-500 mt-1">
                                            @if($search || $statusFilter || $typeFilter)
                                                Try adjusting your filters
                                            @else
                                                No custom domains have been configured yet
                                            @endif
                                        </flux:text>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($domains->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                    {{ $domains->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
