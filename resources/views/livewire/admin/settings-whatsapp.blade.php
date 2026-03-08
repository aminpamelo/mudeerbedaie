<?php

use App\Models\WhatsAppSendLog;
use App\Services\SettingsService;
use App\Services\WhatsAppService;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $enabled = false;

    public string $provider = 'onsend';

    public string $apiToken = '';

    public string $metaPhoneNumberId = '';

    public string $metaAccessToken = '';

    public string $metaWabaId = '';

    public string $metaAppId = '';

    public string $metaAppSecret = '';

    public string $metaVerifyToken = '';

    public string $metaApiVersion = 'v21.0';

    public int $minDelay = 10;

    public int $maxDelay = 30;

    public int $batchSize = 15;

    public int $batchPauseMinutes = 1;

    public int $dailyLimit = 0;

    public bool $timeRestrictionEnabled = false;

    public int $sendHoursStart = 8;

    public int $sendHoursEnd = 22;

    public bool $messageVariationEnabled = false;

    public string $testPhoneNumber = '';

    public string $testMessage = 'This is a test message from the WhatsApp notification system.';

    public array $deviceStatus = [];

    public array $todayStats = [];

    public function mount(): void
    {
        $settingsService = app(SettingsService::class);

        // Load provider selection
        $this->provider = $settingsService->get('whatsapp_provider', 'onsend');

        // Load current settings values
        $this->enabled = (bool) $settingsService->get('whatsapp_enabled', false);
        $this->apiToken = $settingsService->get('whatsapp_api_token', '');

        // Load Meta Cloud API settings
        $this->metaPhoneNumberId = $settingsService->get('meta_phone_number_id', '');
        $this->metaAccessToken = $settingsService->get('meta_access_token', '');
        $this->metaWabaId = $settingsService->get('meta_waba_id', '');
        $this->metaAppId = $settingsService->get('meta_app_id', '');
        $this->metaAppSecret = $settingsService->get('meta_app_secret', '');
        $this->metaVerifyToken = $settingsService->get('meta_verify_token', '');
        $this->metaApiVersion = $settingsService->get('meta_api_version', 'v21.0');

        // Load anti-ban settings
        $this->minDelay = (int) $settingsService->get('whatsapp_min_delay', 10);
        $this->maxDelay = (int) $settingsService->get('whatsapp_max_delay', 30);
        $this->batchSize = (int) $settingsService->get('whatsapp_batch_size', 15);
        $this->batchPauseMinutes = (int) $settingsService->get('whatsapp_batch_pause', 1);
        $this->dailyLimit = (int) $settingsService->get('whatsapp_daily_limit', 0);
        $this->timeRestrictionEnabled = (bool) $settingsService->get('whatsapp_time_restriction', false);
        $this->sendHoursStart = (int) $settingsService->get('whatsapp_send_hours_start', 8);
        $this->sendHoursEnd = (int) $settingsService->get('whatsapp_send_hours_end', 22);
        $this->messageVariationEnabled = (bool) $settingsService->get('whatsapp_message_variation', false);

        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $whatsApp = app(WhatsAppService::class);
        $this->deviceStatus = $whatsApp->checkDeviceStatus();
        $this->todayStats = $whatsApp->getTodayStats();
    }

    public function save(): void
    {
        $rules = [
            'provider' => 'required|in:onsend,meta',
            'minDelay' => 'required|integer|min:5|max:60',
            'maxDelay' => 'required|integer|min:10|max:120',
            'batchSize' => 'required|integer|min:5|max:50',
            'batchPauseMinutes' => 'required|integer|min:1|max:10',
            'dailyLimit' => 'required|integer|min:0|max:10000',
            'sendHoursStart' => 'required|integer|min:0|max:23',
            'sendHoursEnd' => 'required|integer|min:1|max:24',
        ];

        if ($this->provider === 'onsend') {
            $rules['apiToken'] = 'nullable|string|max:500';
        }

        if ($this->provider === 'meta') {
            $rules['metaPhoneNumberId'] = 'required|string|max:255';
            $rules['metaAccessToken'] = 'required|string|max:1000';
            $rules['metaWabaId'] = 'nullable|string|max:255';
            $rules['metaAppId'] = 'nullable|string|max:255';
            $rules['metaAppSecret'] = 'nullable|string|max:500';
            $rules['metaVerifyToken'] = 'nullable|string|max:255';
            $rules['metaApiVersion'] = 'required|string|max:20';
        }

        $this->validate($rules);

        // Validate max delay is greater than min delay
        if ($this->maxDelay <= $this->minDelay) {
            $this->addError('maxDelay', 'Maximum delay must be greater than minimum delay.');

            return;
        }

        // Validate send hours only if time restriction is enabled
        if ($this->timeRestrictionEnabled && $this->sendHoursEnd <= $this->sendHoursStart) {
            $this->addError('sendHoursEnd', 'End time must be greater than start time.');

            return;
        }

        // Save WhatsApp settings
        $settingsService = app(SettingsService::class);
        $settingsService->set('whatsapp_provider', $this->provider, 'string', 'whatsapp');
        $settingsService->set('whatsapp_enabled', $this->enabled, 'boolean', 'whatsapp');

        if ($this->provider === 'onsend') {
            $settingsService->set('whatsapp_api_token', $this->apiToken, 'encrypted', 'whatsapp');
        }

        if ($this->provider === 'meta') {
            $settingsService->set('meta_phone_number_id', $this->metaPhoneNumberId, 'string', 'whatsapp');
            $settingsService->set('meta_access_token', $this->metaAccessToken, 'encrypted', 'whatsapp');
            $settingsService->set('meta_waba_id', $this->metaWabaId, 'string', 'whatsapp');
            $settingsService->set('meta_app_id', $this->metaAppId, 'string', 'whatsapp');
            $settingsService->set('meta_app_secret', $this->metaAppSecret, 'encrypted', 'whatsapp');
            $settingsService->set('meta_verify_token', $this->metaVerifyToken, 'string', 'whatsapp');
            $settingsService->set('meta_api_version', $this->metaApiVersion, 'string', 'whatsapp');
        }

        $settingsService->set('whatsapp_min_delay', $this->minDelay, 'number', 'whatsapp');
        $settingsService->set('whatsapp_max_delay', $this->maxDelay, 'number', 'whatsapp');
        $settingsService->set('whatsapp_batch_size', $this->batchSize, 'number', 'whatsapp');
        $settingsService->set('whatsapp_batch_pause', $this->batchPauseMinutes, 'number', 'whatsapp');
        $settingsService->set('whatsapp_daily_limit', $this->dailyLimit, 'number', 'whatsapp');
        $settingsService->set('whatsapp_time_restriction', $this->timeRestrictionEnabled, 'boolean', 'whatsapp');
        $settingsService->set('whatsapp_send_hours_start', $this->sendHoursStart, 'number', 'whatsapp');
        $settingsService->set('whatsapp_send_hours_end', $this->sendHoursEnd, 'number', 'whatsapp');
        $settingsService->set('whatsapp_message_variation', $this->messageVariationEnabled, 'boolean', 'whatsapp');

        // Update runtime config
        config(['services.whatsapp.provider' => $this->provider]);
        config(['services.onsend.api_token' => $this->apiToken]);
        config(['services.onsend.enabled' => $this->enabled]);
        config(['services.onsend.min_delay_seconds' => $this->minDelay]);
        config(['services.onsend.max_delay_seconds' => $this->maxDelay]);
        config(['services.onsend.batch_size' => $this->batchSize]);
        config(['services.onsend.batch_pause_minutes' => $this->batchPauseMinutes]);
        config(['services.onsend.daily_limit' => $this->dailyLimit]);
        config(['services.onsend.time_restriction_enabled' => $this->timeRestrictionEnabled]);
        config(['services.onsend.send_hours_start' => $this->sendHoursStart]);
        config(['services.onsend.send_hours_end' => $this->sendHoursEnd]);
        config(['services.onsend.message_variation_enabled' => $this->messageVariationEnabled]);

        $this->dispatch('settings-saved');
        $this->refreshStatus();
    }

    public function toggleEnabled(): void
    {
        $this->enabled = ! $this->enabled;
    }

    public function sendTestMessage(): void
    {
        $this->validate([
            'testPhoneNumber' => 'required|string|min:10',
            'testMessage' => 'required|string|min:1|max:1000',
        ]);

        // Save current settings first so WhatsAppManager reads fresh credentials
        $this->save();

        // Resolve via container so WhatsAppManager is injected
        $whatsApp = app(WhatsAppService::class);

        $result = $whatsApp->send($this->testPhoneNumber, $this->testMessage);

        if ($result['success']) {
            $this->dispatch('test-message-success', messageId: $result['message_id'] ?? 'N/A');
        } else {
            $this->dispatch('test-message-failed', error: $result['error'] ?? 'Unknown error');
        }

        $this->refreshStatus();
    }

    public function getRecentLogsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return WhatsAppSendLog::orderByDesc('send_date')
            ->limit(7)
            ->get();
    }

    public function getWeeklyStatsProperty(): array
    {
        $logs = WhatsAppSendLog::thisWeek()->get();

        return [
            'total_messages' => $logs->sum('message_count'),
            'total_success' => $logs->sum('success_count'),
            'total_failures' => $logs->sum('failure_count'),
            'success_rate' => $logs->sum('message_count') > 0
                ? round(($logs->sum('success_count') / $logs->sum('message_count')) * 100, 1)
                : 0,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Warning Banner - Conditional based on provider -->
    @if($provider === 'onsend')
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <flux:icon.exclamation-triangle class="w-6 h-6 text-amber-600" />
                </div>
                <div>
                    <h3 class="font-semibold text-amber-800">Warning: Unofficial API</h3>
                    <div class="text-sm text-amber-700 mt-1 space-y-1">
                        <p>WhatsApp is using an unofficial API (OnSend.io). Risks include:</p>
                        <ul class="list-disc list-inside ml-2 space-y-0.5">
                            <li>Phone number may be blocked by WhatsApp</li>
                            <li>Service may be interrupted without notice</li>
                            <li>Violates WhatsApp Terms of Service</li>
                        </ul>
                        <p class="font-medium mt-2">Recommended: Use for important messages only.</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <flux:icon.check-badge class="w-6 h-6 text-green-600" />
                </div>
                <div>
                    <h3 class="font-semibold text-green-800">Meta Cloud API (Official)</h3>
                    <div class="text-sm text-green-700 mt-1 space-y-1">
                        <p>You are using the official WhatsApp Business Platform API by Meta. Benefits:</p>
                        <ul class="list-disc list-inside ml-2 space-y-0.5">
                            <li>No risk of account being blocked</li>
                            <li>Message template and media support</li>
                            <li>High reliability and speed</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Provider Selection -->
    <flux:card>
        <div class="p-6">
            <div class="mb-6">
                <flux:heading size="lg">WhatsApp Provider</flux:heading>
                <flux:text class="text-gray-500 mt-1">Choose the API provider for sending WhatsApp messages</flux:text>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="relative flex cursor-pointer rounded-lg border p-4 transition-colors {{ $provider === 'onsend' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-500' : 'border-gray-200 hover:border-gray-300' }}">
                    <input type="radio" wire:model.live="provider" value="onsend" class="sr-only" />
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center {{ $provider === 'onsend' ? 'border-blue-500' : 'border-gray-300' }}">
                                @if($provider === 'onsend')
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-900">OnSend.io</span>
                                <flux:badge color="amber" size="sm">Unofficial</flux:badge>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Unofficial API via OnSend.io. Easy to set up but has risk of account being blocked.</p>
                        </div>
                    </div>
                </label>

                <label class="relative flex cursor-pointer rounded-lg border p-4 transition-colors {{ $provider === 'meta' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-500' : 'border-gray-200 hover:border-gray-300' }}">
                    <input type="radio" wire:model.live="provider" value="meta" class="sr-only" />
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center {{ $provider === 'meta' ? 'border-blue-500' : 'border-gray-300' }}">
                                @if($provider === 'meta')
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-900">Meta Cloud API</span>
                                <flux:badge color="green" size="sm">Official</flux:badge>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Official WhatsApp Business Platform API by Meta. Safe and reliable.</p>
                        </div>
                    </div>
                </label>
            </div>
        </div>
    </flux:card>

    <!-- Device Status & Statistics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Device Status Card -->
        <flux:card>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Device Status</flux:heading>
                    <flux:button variant="ghost" size="sm" wire:click="refreshStatus" icon="arrow-path">
                        Refresh
                    </flux:button>
                </div>

                <div class="flex items-center gap-4 p-4 rounded-lg {{ $deviceStatus['status'] === 'connected' ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200' }}">
                    @if($deviceStatus['status'] === 'connected')
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <flux:icon.check-circle class="w-7 h-7 text-green-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-green-800">Connected</p>
                            <p class="text-sm text-green-600">WhatsApp device is active and ready to send messages</p>
                        </div>
                    @elseif($deviceStatus['status'] === 'not_configured')
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                            <flux:icon.cog-6-tooth class="w-7 h-7 text-gray-500" />
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Not Configured</p>
                            <p class="text-sm text-gray-600">Please enter the API token below</p>
                        </div>
                    @else
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <flux:icon.x-circle class="w-7 h-7 text-red-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-red-800">Disconnected</p>
                            <p class="text-sm text-red-600">{{ $deviceStatus['message'] ?? 'Please check device configuration' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </flux:card>

        <!-- Today's Statistics Card -->
        <flux:card>
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">Today's Statistics</flux:heading>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <p class="text-2xl font-bold text-blue-700">{{ $todayStats['message_count'] ?? 0 }}</p>
                        <p class="text-sm text-blue-600">Messages Sent</p>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg">
                        <p class="text-2xl font-bold text-green-700">{{ $todayStats['success_count'] ?? 0 }}</p>
                        <p class="text-sm text-green-600">Successful</p>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg">
                        <p class="text-2xl font-bold text-red-700">{{ $todayStats['failure_count'] ?? 0 }}</p>
                        <p class="text-sm text-red-600">Failed</p>
                    </div>
                    <div class="p-4 bg-amber-50 rounded-lg">
                        @if(($todayStats['is_unlimited'] ?? false) || $dailyLimit <= 0)
                            <p class="text-2xl font-bold text-amber-700">∞</p>
                            <p class="text-sm text-amber-600">No Limit</p>
                        @else
                            <p class="text-2xl font-bold text-amber-700">{{ $todayStats['remaining'] ?? 0 }}</p>
                            <p class="text-sm text-amber-600">Remaining / {{ $dailyLimit }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- API Configuration -->
    <flux:card>
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading size="lg">API Configuration</flux:heading>
                    <flux:text class="text-gray-500 mt-1">Set API credentials and enable WhatsApp service</flux:text>
                </div>
                <div class="flex items-center gap-3">
                    @if($enabled)
                        <flux:badge color="green" size="lg">Active</flux:badge>
                    @else
                        <flux:badge color="zinc" size="lg">Inactive</flux:badge>
                    @endif
                    <flux:switch wire:click="toggleEnabled" :checked="$enabled" />
                </div>
            </div>

            <div class="space-y-6">
                @if($provider === 'onsend')
                    <!-- OnSend API Token -->
                    <flux:field>
                        <flux:label>API Token (OnSend.io)</flux:label>
                        <flux:input
                            type="password"
                            wire:model="apiToken"
                            placeholder="Enter API token from OnSend.io dashboard"
                        />
                        <flux:description>
                            Get token from <a href="https://onsend.io" target="_blank" class="text-blue-600 hover:underline">OnSend.io</a> > Devices > View > Token
                        </flux:description>
                        @error('apiToken') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                @else
                    <!-- Meta Cloud API Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:field>
                            <flux:label>Phone Number ID</flux:label>
                            <flux:input
                                type="text"
                                wire:model="metaPhoneNumberId"
                                placeholder="Example: 123456789012345"
                            />
                            <flux:description>
                                Get from Meta Business Suite > WhatsApp > API Setup
                            </flux:description>
                            @error('metaPhoneNumberId') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Access Token</flux:label>
                            <flux:input
                                type="password"
                                wire:model="metaAccessToken"
                                placeholder="Enter Meta access token"
                            />
                            <flux:description>
                                Permanent access token from Meta Business Suite
                            </flux:description>
                            @error('metaAccessToken') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>WhatsApp Business Account ID (WABA ID)</flux:label>
                            <flux:input
                                type="text"
                                wire:model="metaWabaId"
                                placeholder="Example: 123456789012345"
                            />
                            <flux:description>
                                Your WhatsApp Business Account ID
                            </flux:description>
                            @error('metaWabaId') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>App ID</flux:label>
                            <flux:input
                                type="text"
                                wire:model="metaAppId"
                                placeholder="Example: 123456789012345"
                            />
                            <flux:description>
                                Get from Meta Developers > App Settings > Basic. Required for template document uploads.
                            </flux:description>
                            @error('metaAppId') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>App Secret</flux:label>
                            <flux:input
                                type="password"
                                wire:model="metaAppSecret"
                                placeholder="Enter app secret"
                            />
                            <flux:description>
                                Get from Meta Developers > App Settings > Basic
                            </flux:description>
                            @error('metaAppSecret') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Verify Token (Webhook)</flux:label>
                            <flux:input
                                type="text"
                                wire:model="metaVerifyToken"
                                placeholder="Token for webhook verification"
                            />
                            <flux:description>
                                Custom token for Meta webhook verification
                            </flux:description>
                            @error('metaVerifyToken') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>API Version</flux:label>
                            <flux:input
                                type="text"
                                wire:model="metaApiVersion"
                                placeholder="v21.0"
                            />
                            <flux:description>
                                Meta Graph API version (e.g.: v21.0)
                            </flux:description>
                            @error('metaApiVersion') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                    </div>
                @endif
            </div>
        </div>
    </flux:card>

    <!-- Anti-Ban Settings (OnSend only) -->
    @if($provider === 'onsend')
    <flux:card>
        <div class="p-6">
            <div class="mb-6">
                <flux:heading size="lg">Anti-Ban Settings</flux:heading>
                <flux:text class="text-gray-500 mt-1">Configure safety measures to reduce the risk of account being blocked</flux:text>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-start gap-3">
                    <flux:icon.shield-check class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-green-700">
                        <p class="font-medium">Active safety measures:</p>
                        <ul class="list-disc list-inside mt-1 space-y-0.5">
                            <li>Random delay between messages ({{ $minDelay }}-{{ $maxDelay }} seconds)</li>
                            <li>Pause between message batches (every {{ $batchSize }} messages, {{ $batchPauseMinutes }} minute pause)</li>
                            @if($dailyLimit > 0)
                                <li>Daily limit ({{ $dailyLimit }} messages/day)</li>
                            @else
                                <li class="text-amber-600">Daily limit: No limit (unlimited)</li>
                            @endif
                            @if($timeRestrictionEnabled)
                                <li>Send hours ({{ $sendHoursStart }}:00 - {{ $sendHoursEnd }}:00)</li>
                            @else
                                <li class="text-amber-600">Send hours: No restriction</li>
                            @endif
                            @if($messageVariationEnabled)
                                <li>Automatic message variation for uniqueness</li>
                            @else
                                <li class="text-gray-500">Message variation: Inactive</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Delay Settings -->
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-900">Delay Between Messages</h4>

                    <flux:field>
                        <flux:label>Minimum Delay (seconds)</flux:label>
                        <flux:input type="number" wire:model="minDelay" min="5" max="60" />
                        <flux:description>Minimum 5 seconds recommended</flux:description>
                        @error('minDelay') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Maximum Delay (seconds)</flux:label>
                        <flux:input type="number" wire:model="maxDelay" min="10" max="120" />
                        <flux:description>Maximum 30 seconds recommended</flux:description>
                        @error('maxDelay') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                </div>

                <!-- Batch Settings -->
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-900">Message Batching</h4>

                    <flux:field>
                        <flux:label>Batch Size</flux:label>
                        <flux:input type="number" wire:model="batchSize" min="5" max="50" />
                        <flux:description>Number of messages before pause</flux:description>
                        @error('batchSize') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Pause Duration (minutes)</flux:label>
                        <flux:input type="number" wire:model="batchPauseMinutes" min="1" max="10" />
                        <flux:description>Pause duration between batches</flux:description>
                        @error('batchPauseMinutes') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                </div>

                <!-- Daily Limit -->
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-900">Daily Limit</h4>

                    <flux:field>
                        <flux:label>Daily Message Limit</flux:label>
                        <flux:input type="number" wire:model="dailyLimit" min="0" max="10000" />
                        <flux:description>Enter 0 for no limit (unlimited)</flux:description>
                        @error('dailyLimit') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                </div>

                <!-- Send Hours -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-900">Send Hours</h4>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.live="timeRestrictionEnabled" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="text-sm text-gray-600">Enable time restriction</span>
                        </label>
                    </div>

                    @if($timeRestrictionEnabled)
                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Start (Hour)</flux:label>
                                <flux:input type="number" wire:model="sendHoursStart" min="0" max="23" />
                                @error('sendHoursStart') <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>

                            <flux:field>
                                <flux:label>End (Hour)</flux:label>
                                <flux:input type="number" wire:model="sendHoursEnd" min="1" max="24" />
                                @error('sendHoursEnd') <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                        </div>
                        <flux:description>Messages will only be sent between these hours (24-hour format)</flux:description>
                    @else
                        <div class="p-3 bg-gray-50 rounded-lg text-sm text-gray-600">
                            Messages can be sent at any time (24 hours)
                        </div>
                    @endif
                </div>
            </div>

            <!-- Message Variation Setting -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex items-start justify-between">
                    <div>
                        <h4 class="font-medium text-gray-900">Message Variation (Unicode)</h4>
                        <p class="text-sm text-gray-500 mt-1">Adds invisible characters to make each message unique</p>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="messageVariationEnabled" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-600">Active</span>
                    </label>
                </div>
                @if($messageVariationEnabled)
                    <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <flux:icon.exclamation-triangle class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                            <p class="text-sm text-amber-700">
                                <strong>Warning:</strong> Excessive use of Unicode characters may be detected by WhatsApp. Use with caution.
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200 flex justify-end">
                <flux:button variant="primary" wire:click="save" icon="check">
                    Save Settings
                </flux:button>
            </div>
        </div>
    </flux:card>
    @endif

    <!-- Save Button (when anti-ban is hidden for Meta provider) -->
    @if($provider === 'meta')
        <div class="flex justify-end">
            <flux:button variant="primary" wire:click="save" icon="check">
                Save Settings
            </flux:button>
        </div>
    @endif

    <!-- Test Message -->
    <flux:card>
        <div class="p-6">
            <div class="mb-6">
                <flux:heading size="lg">Send Test Message</flux:heading>
                <flux:text class="text-gray-500 mt-1">Test the connection by sending a message to a phone number</flux:text>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>Phone Number</flux:label>
                    <flux:input
                        type="text"
                        wire:model="testPhoneNumber"
                        placeholder="60123456789"
                    />
                    <flux:description>Enter with country code (without +)</flux:description>
                    @error('testPhoneNumber') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Message</flux:label>
                    <flux:textarea
                        wire:model="testMessage"
                        rows="3"
                        placeholder="Enter test message..."
                    />
                    @error('testMessage') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <div class="mt-4 flex justify-end">
                <flux:button
                    variant="outline"
                    wire:click="sendTestMessage"
                    wire:loading.attr="disabled"
                    icon="paper-airplane"
                >
                    <span wire:loading.remove wire:target="sendTestMessage">Send Test Message</span>
                    <span wire:loading wire:target="sendTestMessage">Sending...</span>
                </flux:button>
            </div>
        </div>
    </flux:card>

    <!-- Recent Activity -->
    <flux:card>
        <div class="p-6">
            <div class="mb-6">
                <flux:heading size="lg">Send History (7 Days)</flux:heading>
                <flux:text class="text-gray-500 mt-1">Summary of WhatsApp sending activity</flux:text>
            </div>

            @if($this->recentLogs->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Date</th>
                                <th class="text-center py-3 px-4 text-sm font-medium text-gray-600">Total</th>
                                <th class="text-center py-3 px-4 text-sm font-medium text-gray-600">Success</th>
                                <th class="text-center py-3 px-4 text-sm font-medium text-gray-600">Failed</th>
                                <th class="text-center py-3 px-4 text-sm font-medium text-gray-600">Success Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->recentLogs as $log)
                                <tr wire:key="log-{{ $log->id }}" class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <span class="font-medium text-gray-900">{{ $log->send_date->format('d M Y') }}</span>
                                        <span class="text-xs text-gray-500 ml-2">({{ $log->send_date->locale('en')->dayName }})</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="font-semibold text-gray-900">{{ $log->message_count }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="text-green-600 font-medium">{{ $log->success_count }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="text-red-600 font-medium">{{ $log->failure_count }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        @php
                                            $rate = $log->success_rate;
                                            $color = $rate >= 90 ? 'green' : ($rate >= 70 ? 'yellow' : 'red');
                                        @endphp
                                        <flux:badge :color="$color">{{ $rate }}%</flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Weekly Summary -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-4">This Week's Summary</h4>
                    <div class="grid grid-cols-4 gap-4">
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-xl font-bold text-gray-900">{{ $this->weeklyStats['total_messages'] }}</p>
                            <p class="text-xs text-gray-600">Total Messages</p>
                        </div>
                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <p class="text-xl font-bold text-green-700">{{ $this->weeklyStats['total_success'] }}</p>
                            <p class="text-xs text-green-600">Success</p>
                        </div>
                        <div class="text-center p-3 bg-red-50 rounded-lg">
                            <p class="text-xl font-bold text-red-700">{{ $this->weeklyStats['total_failures'] }}</p>
                            <p class="text-xs text-red-600">Failed</p>
                        </div>
                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <p class="text-xl font-bold text-blue-700">{{ $this->weeklyStats['success_rate'] }}%</p>
                            <p class="text-xs text-blue-600">Success Rate</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <flux:icon.chart-bar class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                    <p class="text-gray-600">No send history yet.</p>
                    <p class="text-sm text-gray-500 mt-1">Send your first message to see statistics.</p>
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Toast Notifications -->
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:settings-saved.window="show = true; message = 'Settings saved successfully'; type = 'success'; setTimeout(() => show = false, 4000)"
        x-on:test-message-success.window="show = true; message = 'Test message sent successfully! ID: ' + $event.detail.messageId; type = 'success'; setTimeout(() => show = false, 4000)"
        x-on:test-message-failed.window="show = true; message = 'Failed to send message: ' + $event.detail.error; type = 'error'; setTimeout(() => show = false, 6000)"
        x-show="show"
        x-transition
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div
            x-show="type === 'success'"
            class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg shadow-lg"
        >
            <flux:icon.check-circle class="w-5 h-5 text-green-600" />
            <span x-text="message"></span>
        </div>
        <div
            x-show="type === 'error'"
            class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg shadow-lg"
        >
            <flux:icon.exclamation-circle class="w-5 h-5 text-red-600" />
            <span x-text="message"></span>
        </div>
    </div>
</div>
