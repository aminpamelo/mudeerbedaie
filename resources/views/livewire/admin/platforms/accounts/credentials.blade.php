<?php

use Livewire\Volt\Component;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;

new class extends Component {
    public Platform $platform;
    public PlatformAccount $account;
    public $credentials;

    // Form fields for new credential
    public $showCreateForm = false;
    public $credential_type = '';
    public $name = '';
    public $value = '';
    public $refresh_token = '';
    public $scopes = '';
    public $expires_at = '';
    public $auto_refresh = false;

    // Edit mode
    public $editing = null;
    public $edit_name = '';
    public $edit_value = '';
    public $edit_refresh_token = '';
    public $edit_scopes = '';
    public $edit_expires_at = '';
    public $edit_auto_refresh = false;

    public function mount(Platform $platform, PlatformAccount $account)
    {
        $this->platform = $platform;
        $this->account = $account;
        $this->loadCredentials();
    }

    public function loadCredentials()
    {
        $this->credentials = $this->account->credentials()
            ->orderBy('credential_type')
            ->orderBy('name')
            ->get();
    }

    public function rules()
    {
        return [
            'credential_type' => 'required|string|in:api_key,access_token,app_secret,refresh_token,webhook_secret,oauth_token,shop_id,seller_id,business_manager_id,custom',
            'name' => 'required|string|max:255',
            'value' => 'required|string|max:1000',
            'refresh_token' => 'nullable|string|max:1000',
            'scopes' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
            'auto_refresh' => 'boolean',
        ];
    }

    public function create()
    {
        $validated = $this->validate();

        $credential = new PlatformApiCredential([
            'platform_id' => $this->platform->id,
            'platform_account_id' => $this->account->id,
            'credential_type' => $validated['credential_type'],
            'name' => $validated['name'],
            'scopes' => $validated['scopes'] ? array_map('trim', explode(',', $validated['scopes'])) : [],
            'expires_at' => $validated['expires_at'] ?: null,
            'is_active' => true,
            'auto_refresh' => $validated['auto_refresh'],
        ]);

        $credential->setValue($validated['value']);

        if ($validated['refresh_token']) {
            $credential->setRefreshToken($validated['refresh_token']);
        }

        $credential->save();

        $this->resetForm();
        $this->loadCredentials();

        $this->dispatch('credential-created', [
            'message' => "Credential '{$credential->name}' has been created successfully"
        ]);
    }

    public function edit($credentialId)
    {
        $credential = $this->credentials->find($credentialId);
        if (!$credential) return;

        $this->editing = $credentialId;
        $this->edit_name = $credential->name;
        $this->edit_value = ''; // Don't show encrypted values
        $this->edit_refresh_token = '';
        $this->edit_scopes = $credential->scopes ? implode(', ', $credential->scopes) : '';
        $this->edit_expires_at = $credential->expires_at ? $credential->expires_at->format('Y-m-d\TH:i') : '';
        $this->edit_auto_refresh = $credential->auto_refresh;
    }

    public function update()
    {
        $this->validate([
            'edit_name' => 'required|string|max:255',
            'edit_value' => 'nullable|string|max:1000',
            'edit_refresh_token' => 'nullable|string|max:1000',
            'edit_scopes' => 'nullable|string|max:500',
            'edit_expires_at' => 'nullable|date|after:now',
            'edit_auto_refresh' => 'boolean',
        ]);

        $credential = PlatformApiCredential::find($this->editing);
        if (!$credential) return;

        $updateData = [
            'name' => $this->edit_name,
            'scopes' => $this->edit_scopes ? array_map('trim', explode(',', $this->edit_scopes)) : [],
            'expires_at' => $this->edit_expires_at ?: null,
            'auto_refresh' => $this->edit_auto_refresh,
        ];

        if ($this->edit_value) {
            $credential->setValue($this->edit_value);
        }

        if ($this->edit_refresh_token) {
            $credential->setRefreshToken($this->edit_refresh_token);
        }

        $credential->update($updateData);

        $this->cancelEdit();
        $this->loadCredentials();

        $this->dispatch('credential-updated', [
            'message' => "Credential '{$credential->name}' has been updated successfully"
        ]);
    }

    public function delete($credentialId)
    {
        $credential = PlatformApiCredential::find($credentialId);
        if (!$credential) return;

        $credentialName = $credential->name;
        $credential->delete();

        $this->loadCredentials();

        $this->dispatch('credential-deleted', [
            'message' => "Credential '{$credentialName}' has been deleted successfully"
        ]);
    }

    public function toggleStatus($credentialId)
    {
        $credential = PlatformApiCredential::find($credentialId);
        if (!$credential) return;

        $credential->update(['is_active' => !$credential->is_active]);
        $this->loadCredentials();

        $status = $credential->is_active ? 'activated' : 'deactivated';
        $this->dispatch('credential-status-changed', [
            'message' => "Credential '{$credential->name}' has been {$status}"
        ]);
    }

    public function resetForm()
    {
        $this->showCreateForm = false;
        $this->credential_type = '';
        $this->name = '';
        $this->value = '';
        $this->refresh_token = '';
        $this->scopes = '';
        $this->expires_at = '';
        $this->auto_refresh = false;
    }

    public function cancelEdit()
    {
        $this->editing = null;
        $this->edit_name = '';
        $this->edit_value = '';
        $this->edit_refresh_token = '';
        $this->edit_scopes = '';
        $this->edit_expires_at = '';
        $this->edit_auto_refresh = false;
    }

    public function getCredentialTypeOptions()
    {
        return [
            'api_key' => 'API Key',
            'access_token' => 'Access Token',
            'app_secret' => 'App Secret',
            'refresh_token' => 'Refresh Token',
            'webhook_secret' => 'Webhook Secret',
            'oauth_token' => 'OAuth Token',
            'shop_id' => 'Shop ID',
            'seller_id' => 'Seller ID',
            'business_manager_id' => 'Business Manager ID',
            'custom' => 'Custom',
        ];
    }

    public function getCredentialIcon($type)
    {
        return match($type) {
            'api_key' => 'key',
            'access_token' => 'lock-closed',
            'app_secret' => 'shield-check',
            'refresh_token' => 'arrow-path',
            'webhook_secret' => 'globe-alt',
            'oauth_token' => 'identification',
            'shop_id' => 'building-storefront',
            'seller_id' => 'user-circle',
            'business_manager_id' => 'briefcase',
            default => 'variable',
        };
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
                                Back to Platforms
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
                        <flux:button variant="ghost" size="sm" :href="route('platforms.accounts.show', [$platform, $account])" wire:navigate class="ml-4">
                            {{ $account->name }}
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">API Credentials</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">API Credentials</flux:heading>
            <flux:text class="mt-2">Manage API credentials for {{ $account->name }} on {{ $platform->display_name }}</flux:text>
        </div>
        <flux:button variant="primary" wire:click="$toggle('showCreateForm')">
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                Add Credential
            </div>
        </flux:button>
    </div>

    {{-- Create Credential Form --}}
    @if($showCreateForm)
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
        <flux:heading size="lg" class="mb-4">Add New Credential</flux:heading>

        <form wire:submit="create" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <flux:field>
                        <flux:label>Credential Type *</flux:label>
                        <flux:select wire:model="credential_type">
                            <flux:select.option value="">Select type...</flux:select.option>
                            @foreach($this->getCredentialTypeOptions() as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="credential_type" />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Name *</flux:label>
                        <flux:input wire:model="name" placeholder="e.g., Production API Key" />
                        <flux:description>A descriptive name for this credential</flux:description>
                        <flux:error name="name" />
                    </flux:field>
                </div>

                <div class="md:col-span-2">
                    <flux:field>
                        <flux:label>Value *</flux:label>
                        <flux:input wire:model="value" type="password" placeholder="Enter the credential value" />
                        <flux:description>The credential value will be encrypted and stored securely</flux:description>
                        <flux:error name="value" />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Refresh Token</flux:label>
                        <flux:input wire:model="refresh_token" type="password" placeholder="Enter refresh token if applicable" />
                        <flux:description>Optional refresh token for OAuth flows</flux:description>
                        <flux:error name="refresh_token" />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Scopes</flux:label>
                        <flux:input wire:model="scopes" placeholder="read, write, admin" />
                        <flux:description>Comma-separated list of scopes or permissions</flux:description>
                        <flux:error name="scopes" />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Expires At</flux:label>
                        <flux:input wire:model="expires_at" type="datetime-local" />
                        <flux:description>When this credential expires (optional)</flux:description>
                        <flux:error name="expires_at" />
                    </flux:field>
                </div>

                <div class="flex items-center">
                    <flux:field>
                        <flux:checkbox wire:model="auto_refresh" />
                        <flux:label>Auto-refresh when expired</flux:label>
                        <flux:description>Automatically refresh this credential when it expires</flux:description>
                    </flux:field>
                </div>
            </div>

            <div class="flex items-center justify-between pt-6">
                <flux:button variant="ghost" wire:click="resetForm">
                    <div class="flex items-center justify-center">
                        <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                        Cancel
                    </div>
                </flux:button>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <div class="flex items-center justify-center">
                        <flux:icon name="plus" class="w-4 h-4 mr-1" wire:loading.remove />
                        <flux:icon name="loading" class="w-4 h-4 mr-1 animate-spin" wire:loading />
                        <span wire:loading.remove>Create Credential</span>
                        <span wire:loading>Creating...</span>
                    </div>
                </flux:button>
            </div>
        </form>
    </div>
    @endif

    {{-- Credentials List --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
        @if($credentials->count() > 0)
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">Credentials</flux:heading>
                <div class="space-y-4">
                    @foreach($credentials as $credential)
                        <div class="border rounded-lg p-4">
                            @if($editing === $credential->id)
                                {{-- Edit Form --}}
                                <form wire:submit="update" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <flux:field>
                                                <flux:label>Name</flux:label>
                                                <flux:input wire:model="edit_name" />
                                                <flux:error name="edit_name" />
                                            </flux:field>
                                        </div>

                                        <div>
                                            <flux:field>
                                                <flux:label>Value</flux:label>
                                                <flux:input wire:model="edit_value" type="password" placeholder="Leave empty to keep current value" />
                                                <flux:error name="edit_value" />
                                            </flux:field>
                                        </div>

                                        <div>
                                            <flux:field>
                                                <flux:label>Scopes</flux:label>
                                                <flux:input wire:model="edit_scopes" />
                                                <flux:error name="edit_scopes" />
                                            </flux:field>
                                        </div>

                                        <div>
                                            <flux:field>
                                                <flux:label>Expires At</flux:label>
                                                <flux:input wire:model="edit_expires_at" type="datetime-local" />
                                                <flux:error name="edit_expires_at" />
                                            </flux:field>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <flux:field>
                                            <flux:checkbox wire:model="edit_auto_refresh" />
                                            <flux:label>Auto-refresh when expired</flux:label>
                                        </flux:field>

                                        <div class="flex gap-2">
                                            <flux:button variant="ghost" wire:click="cancelEdit">
                                                <div class="flex items-center justify-center">
                                                    <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                                                    Cancel
                                                </div>
                                            </flux:button>
                                            <flux:button type="submit" variant="primary" size="sm">
                                                <div class="flex items-center justify-center">
                                                    <flux:icon name="check" class="w-4 h-4 mr-1" />
                                                    Save
                                                </div>
                                            </flux:button>
                                        </div>
                                    </div>
                                </form>
                            @else
                                {{-- Display Mode --}}
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                                <flux:icon name="{{ $this->getCredentialIcon($credential->credential_type) }}" class="w-5 h-5 text-blue-600" />
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2">
                                                <flux:text class="font-medium">{{ $credential->name }}</flux:text>
                                                @if($credential->is_active)
                                                    <flux:badge size="sm" color="green">Active</flux:badge>
                                                @else
                                                    <flux:badge size="sm" color="red">Inactive</flux:badge>
                                                @endif
                                                @if($credential->isExpired())
                                                    <flux:badge size="sm" color="red">Expired</flux:badge>
                                                @elseif($credential->isExpiringSoon())
                                                    <flux:badge size="sm" color="amber">Expires Soon</flux:badge>
                                                @endif
                                            </div>
                                            <div class="flex items-center space-x-4 mt-1">
                                                <flux:text size="sm" class="text-zinc-600">{{ $this->getCredentialTypeOptions()[$credential->credential_type] ?? $credential->credential_type }}</flux:text>
                                                @if($credential->expires_at)
                                                    <flux:text size="sm" class="text-zinc-600">Expires: {{ $credential->expires_at->format('M j, Y') }}</flux:text>
                                                @endif
                                                @if($credential->last_used_at)
                                                    <flux:text size="sm" class="text-zinc-600">Last used: {{ $credential->last_used_at->diffForHumans() }}</flux:text>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="toggleStatus({{ $credential->id }})"
                                            wire:confirm="Are you sure you want to {{ $credential->is_active ? 'deactivate' : 'activate' }} this credential?"
                                        >
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="{{ $credential->is_active ? 'pause' : 'play' }}" class="w-4 h-4 mr-1" />
                                                {{ $credential->is_active ? 'Deactivate' : 'Activate' }}
                                            </div>
                                        </flux:button>

                                        <flux:button variant="ghost" size="sm" wire:click="edit({{ $credential->id }})">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                                                Edit
                                            </div>
                                        </flux:button>

                                        <flux:button
                                            variant="danger"
                                            size="sm"
                                            wire:click="delete({{ $credential->id }})"
                                            wire:confirm="Are you sure you want to delete this credential? This action cannot be undone."
                                        >
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="trash" class="w-4 h-4 mr-1" />
                                                Delete
                                            </div>
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="p-12 text-center">
                <div class="mx-auto w-12 h-12 bg-zinc-100 rounded-lg flex items-center justify-center mb-4">
                    <flux:icon name="key" class="w-6 h-6 text-zinc-400" />
                </div>
                <flux:heading size="lg" class="mb-2">No API Credentials</flux:heading>
                <flux:text class="text-zinc-600 mb-4">
                    You haven't added any API credentials for this account yet.
                </flux:text>
                <flux:button variant="primary" wire:click="$toggle('showCreateForm')">
                    <div class="flex items-center justify-center">
                        <flux:icon name="plus" class="w-4 h-4 mr-1" />
                        Add Your First Credential
                    </div>
                </flux:button>
            </div>
        @endif
    </div>

    {{-- API Testing & Validation (Coming Soon) --}}
    <div class="mt-6 bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg border border-green-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg" class="text-green-900">API Testing & Validation</flux:heading>
            <flux:badge size="sm" color="green">Coming Soon</flux:badge>
        </div>

        <flux:text size="sm" class="text-green-800 mb-4">
            Advanced API testing and validation features will be available to ensure your credentials work correctly:
        </flux:text>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-3">
                <div class="flex items-center space-x-3 opacity-70">
                    <flux:icon name="shield-check" class="w-4 h-4 text-green-600" />
                    <flux:text size="sm" class="text-green-800">Test API Connection</flux:text>
                </div>

                <div class="flex items-center space-x-3 opacity-70">
                    <flux:icon name="bolt" class="w-4 h-4 text-green-600" />
                    <flux:text size="sm" class="text-green-800">Validate Permissions</flux:text>
                </div>

                <div class="flex items-center space-x-3 opacity-70">
                    <flux:icon name="chart-bar" class="w-4 h-4 text-green-600" />
                    <flux:text size="sm" class="text-green-800">Monitor API Health</flux:text>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex items-center space-x-3 opacity-70">
                    <flux:icon name="arrow-path" class="w-4 h-4 text-green-600" />
                    <flux:text size="sm" class="text-green-800">Auto Token Refresh</flux:text>
                </div>

                <div class="flex items-center space-x-3 opacity-70">
                    <flux:icon name="bell" class="w-4 h-4 text-green-600" />
                    <flux:text size="sm" class="text-green-800">Expiration Alerts</flux:text>
                </div>

                <div class="flex items-center space-x-3 opacity-70">
                    <flux:icon name="document-text" class="w-4 h-4 text-green-600" />
                    <flux:text size="sm" class="text-green-800">Usage Analytics</flux:text>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-green-200">
            <flux:text size="xs" class="text-green-700">
                🔧 These tools will help ensure your API credentials are working properly and performing optimally.
            </flux:text>
        </div>
    </div>
</div>