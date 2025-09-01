<?php
use App\Services\SettingsService;
use Livewire\Volt\Component;

new class extends Component {
    public $site_name = '';
    public $site_description = '';
    public $admin_email = '';
    public $timezone = '';
    public $language = '';
    public $date_format = '';
    public $time_format = '';

    private SettingsService $settingsService;

    public function mount(): void
    {
        $this->settingsService = app(SettingsService::class);
        
        // Load current settings values
        $this->site_name = $this->settingsService->get('site_name', 'Mudeer Bedaie');
        $this->site_description = $this->settingsService->get('site_description', 'Educational Management System');
        $this->admin_email = $this->settingsService->get('admin_email', 'admin@example.com');
        $this->timezone = $this->settingsService->get('timezone', 'Asia/Kuala_Lumpur');
        $this->language = $this->settingsService->get('language', 'en');
        $this->date_format = $this->settingsService->get('date_format', 'd/m/Y');
        $this->time_format = $this->settingsService->get('time_format', 'h:i A');
    }

    public function save(): void
    {
        $this->validate([
            'site_name' => 'required|string|max:255',
            'site_description' => 'nullable|string|max:500',
            'admin_email' => 'required|email|max:255',
            'timezone' => 'required|string',
            'language' => 'required|string|max:10',
            'date_format' => 'required|string|max:20',
            'time_format' => 'required|string|max:20',
        ]);

        // Get settings service
        $settingsService = app(SettingsService::class);

        // Save all settings
        $settingsService->set('site_name', $this->site_name, 'string', 'general');
        $settingsService->set('site_description', $this->site_description, 'text', 'general');
        $settingsService->set('admin_email', $this->admin_email, 'string', 'general');
        $settingsService->set('timezone', $this->timezone, 'string', 'general');
        $settingsService->set('language', $this->language, 'string', 'general');
        $settingsService->set('date_format', $this->date_format, 'string', 'general');
        $settingsService->set('time_format', $this->time_format, 'string', 'general');

        $this->dispatch('settings-saved');
    }

    public function getTimezones(): array
    {
        return [
            'Asia/Kuala_Lumpur' => 'Asia/Kuala Lumpur (MYT)',
            'UTC' => 'UTC',
            'America/New_York' => 'America/New York (EST)',
            'Europe/London' => 'Europe/London (GMT)',
            'Asia/Singapore' => 'Asia/Singapore (SGT)',
            'Asia/Jakarta' => 'Asia/Jakarta (WIB)',
            'Asia/Bangkok' => 'Asia/Bangkok (ICT)',
            'Asia/Manila' => 'Asia/Manila (PST)',
            'Australia/Sydney' => 'Australia/Sydney (AEDT)',
        ];
    }

    public function getLanguages(): array
    {
        return [
            'en' => 'English',
            'ms' => 'Bahasa Malaysia',
            'zh' => '中文 (Chinese)',
            'ta' => 'தமிழ் (Tamil)',
        ];
    }

    public function getDateFormats(): array
    {
        return [
            'd/m/Y' => 'DD/MM/YYYY (31/12/2025)',
            'm/d/Y' => 'MM/DD/YYYY (12/31/2025)',
            'Y-m-d' => 'YYYY-MM-DD (2025-12-31)',
            'd-m-Y' => 'DD-MM-YYYY (31-12-2025)',
            'F j, Y' => 'Month DD, YYYY (December 31, 2025)',
        ];
    }

    public function getTimeFormats(): array
    {
        return [
            'h:i A' => '12:30 PM',
            'H:i' => '12:30',
            'g:i A' => '12:30 PM',
            'G:i' => '12:30',
        ];
    }

    public function layout(): string
    {
        return 'components.admin.settings-layout';
    }

    public function layoutData(): array
    {
        return [
            'title' => 'General Settings',
            'activeTab' => 'general'
        ];
    }
}
?>

<div>
    <flux:card>
            <form wire:submit="save" class="space-y-6">
                <!-- Site Information -->
                <div>
                    <flux:heading size="lg" class="mb-4">Site Information</flux:heading>
                    
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Site Name *</flux:label>
                            <flux:input 
                                wire:model="site_name" 
                                placeholder="Enter site name"
                            />
                            <flux:error name="site_name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Admin Email *</flux:label>
                            <flux:input 
                                type="email"
                                wire:model="admin_email" 
                                placeholder="admin@example.com"
                            />
                            <flux:error name="admin_email" />
                        </flux:field>
                    </div>

                    <flux:field class="mt-6">
                        <flux:label>Site Description</flux:label>
                        <flux:textarea 
                            wire:model="site_description" 
                            placeholder="Brief description of your website"
                            rows="3"
                        />
                        <flux:error name="site_description" />
                    </flux:field>
                </div>

                <flux:separator />

                <!-- Localization Settings -->
                <div>
                    <flux:heading size="lg" class="mb-4">Localization</flux:heading>
                    
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Timezone *</flux:label>
                            <flux:select wire:model="timezone">
                                @foreach($this->getTimezones() as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="timezone" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Language *</flux:label>
                            <flux:select wire:model="language">
                                @foreach($this->getLanguages() as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="language" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Date Format *</flux:label>
                            <flux:select wire:model="date_format">
                                @foreach($this->getDateFormats() as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="date_format" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Time Format *</flux:label>
                            <flux:select wire:model="time_format">
                                @foreach($this->getTimeFormats() as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="time_format" />
                        </flux:field>
                    </div>
                </div>

                <flux:separator />

                <!-- Save Button -->
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">
                        Save General Settings
                    </flux:button>
                </div>
            </form>
        </flux:card>

        <!-- Success Message -->
        <div 
            x-data="{ show: false }"
            x-on:settings-saved.window="show = true; setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition
            class="fixed top-4 right-4 z-50"
        >
            <flux:badge color="emerald" size="lg">
                <flux:icon icon="check-circle" class="w-4 h-4 mr-2" />
                Settings saved successfully!
            </flux:badge>
        </div>
</div>