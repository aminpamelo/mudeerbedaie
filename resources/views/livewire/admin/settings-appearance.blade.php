<?php
use App\Services\SettingsService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $logo;
    public $favicon;
    public $primary_color = '';
    public $secondary_color = '';
    public $footer_text = '';

    public $current_logo = null;
    public $current_favicon = null;

    private function settingsService(): SettingsService
    {
        return app(SettingsService::class);
    }

    public function mount(): void
    {
        // Load current settings values
        $this->primary_color = $this->settingsService()->get('primary_color', '#3B82F6');
        $this->secondary_color = $this->settingsService()->get('secondary_color', '#10B981');
        $this->footer_text = $this->settingsService()->get('footer_text', '© 2025 Mudeer Bedaie. All rights reserved.');
        
        // Get current logo and favicon URLs
        $this->current_logo = $this->settingsService()->getLogo();
        $this->current_favicon = $this->settingsService()->getFavicon();
    }

    public function save(): void
    {
        $this->validate([
            'logo' => 'nullable|image|max:2048', // 2MB max
            'favicon' => 'nullable|image|max:512', // 512KB max
            'primary_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'secondary_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'footer_text' => 'nullable|string|max:255',
        ]);

        // Handle logo upload
        if ($this->logo) {
            $this->settingsService()->setFile('logo_path', $this->logo, 'appearance', 'Website logo file path');
            $this->current_logo = $this->settingsService()->getLogo();
            $this->logo = null; // Reset the file input
        }

        // Handle favicon upload
        if ($this->favicon) {
            $this->settingsService()->setFile('favicon_path', $this->favicon, 'appearance', 'Website favicon file path');
            $this->current_favicon = $this->settingsService()->getFavicon();
            $this->favicon = null; // Reset the file input
        }

        // Save color and text settings
        $this->settingsService()->set('primary_color', $this->primary_color, 'string', 'appearance');
        $this->settingsService()->set('secondary_color', $this->secondary_color, 'string', 'appearance');
        $this->settingsService()->set('footer_text', $this->footer_text, 'text', 'appearance');

        $this->dispatch('settings-saved');
    }

    public function removeLogo(): void
    {
        $this->settingsService()->set('logo_path', null, 'file', 'appearance');
        $this->current_logo = null;
        $this->dispatch('settings-saved');
    }

    public function removeFavicon(): void
    {
        $this->settingsService()->set('favicon_path', null, 'file', 'appearance');
        $this->current_favicon = null;
        $this->dispatch('settings-saved');
    }

    public function layout(): string
    {
        return 'components.admin.settings-layout';
    }

    public function layoutData(): array
    {
        return [
            'title' => 'Appearance Settings',
            'activeTab' => 'appearance'
        ];
    }
}
?>

<div>
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <!-- Logo Settings -->
            <div>
                <flux:heading size="lg" class="mb-4">Logo & Branding</flux:heading>
                
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Logo Upload -->
                    <div>
                        <flux:field>
                            <flux:label>Website Logo</flux:label>
                            <flux:description>
                                Upload a logo for your website. Recommended size: 200x50px. Max file size: 2MB.
                            </flux:description>
                            
                            @if($current_logo)
                                <div class="mt-2 mb-4">
                                    <img src="{{ $current_logo }}" alt="Current Logo" class="h-12 object-contain">
                                    <flux:button 
                                        type="button" 
                                        variant="ghost" 
                                        size="sm" 
                                        wire:click="removeLogo"
                                        class="mt-2"
                                    >
                                        Remove Logo
                                    </flux:button>
                                </div>
                            @endif
                            
                            <flux:input type="file" wire:model="logo" accept="image/*" />
                            <flux:error name="logo" />
                            
                            @if($logo)
                                <div class="mt-2">
                                    <img src="{{ $logo->temporaryUrl() }}" alt="Logo Preview" class="h-12 object-contain border rounded">
                                    <flux:text size="sm" class="text-gray-600">Preview of new logo</flux:text>
                                </div>
                            @endif
                        </flux:field>
                    </div>

                    <!-- Favicon Upload -->
                    <div>
                        <flux:field>
                            <flux:label>Favicon</flux:label>
                            <flux:description>
                                Upload a favicon for your website. Recommended size: 32x32px. Max file size: 512KB.
                            </flux:description>
                            
                            @if($current_favicon)
                                <div class="mt-2 mb-4">
                                    <img src="{{ $current_favicon }}" alt="Current Favicon" class="h-8 w-8 object-contain">
                                    <flux:button 
                                        type="button" 
                                        variant="ghost" 
                                        size="sm" 
                                        wire:click="removeFavicon"
                                        class="mt-2"
                                    >
                                        Remove Favicon
                                    </flux:button>
                                </div>
                            @endif
                            
                            <flux:input type="file" wire:model="favicon" accept="image/*" />
                            <flux:error name="favicon" />
                            
                            @if($favicon)
                                <div class="mt-2">
                                    <img src="{{ $favicon->temporaryUrl() }}" alt="Favicon Preview" class="h-8 w-8 object-contain border rounded">
                                    <flux:text size="sm" class="text-gray-600">Preview of new favicon</flux:text>
                                </div>
                            @endif
                        </flux:field>
                    </div>
                </div>
            </div>

            <flux:separator />

            <!-- Color Settings -->
            <div>
                <flux:heading size="lg" class="mb-4">Color Scheme</flux:heading>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Primary Color</flux:label>
                        <flux:description>
                            Main brand color used for buttons, links, and highlights.
                        </flux:description>
                        <div class="flex items-center space-x-3">
                            <input 
                                type="color" 
                                wire:model.live="primary_color" 
                                class="h-10 w-20 rounded border border-gray-300 dark:border-gray-600"
                            >
                            <flux:input 
                                wire:model="primary_color" 
                                placeholder="#3B82F6"
                                class="flex-1"
                            />
                        </div>
                        <flux:error name="primary_color" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Secondary Color</flux:label>
                        <flux:description>
                            Secondary brand color used for accents and secondary actions.
                        </flux:description>
                        <div class="flex items-center space-x-3">
                            <input 
                                type="color" 
                                wire:model.live="secondary_color" 
                                class="h-10 w-20 rounded border border-gray-300 dark:border-gray-600"
                            >
                            <flux:input 
                                wire:model="secondary_color" 
                                placeholder="#10B981"
                                class="flex-1"
                            />
                        </div>
                        <flux:error name="secondary_color" />
                    </flux:field>
                </div>

                <!-- Color Preview -->
                <div class="mt-6 p-4 border rounded-lg dark:border-gray-600">
                    <flux:text class="font-medium mb-3">Color Preview</flux:text>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2">
                            <div 
                                class="w-6 h-6 rounded" 
                                style="background-color: {{ $primary_color }}"
                            ></div>
                            <flux:text size="sm">Primary</flux:text>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div 
                                class="w-6 h-6 rounded" 
                                style="background-color: {{ $secondary_color }}"
                            ></div>
                            <flux:text size="sm">Secondary</flux:text>
                        </div>
                    </div>
                </div>
            </div>

            <flux:separator />

            <!-- Footer Settings -->
            <div>
                <flux:heading size="lg" class="mb-4">Footer</flux:heading>
                
                <flux:field>
                    <flux:label>Footer Text</flux:label>
                    <flux:description>
                        Text displayed in the website footer. HTML is not allowed.
                    </flux:description>
                    <flux:input 
                        wire:model="footer_text" 
                        placeholder="© 2025 Mudeer Bedaie. All rights reserved."
                    />
                    <flux:error name="footer_text" />
                </flux:field>
            </div>

            <flux:separator />

            <!-- Save Button -->
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">
                    Save Appearance Settings
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