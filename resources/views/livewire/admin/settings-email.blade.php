<?php
use App\Services\SettingsService;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Mail;

new class extends Component {
    public $mail_from_address = '';
    public $mail_from_name = '';
    public $smtp_host = '';
    public $smtp_port = 587;
    public $smtp_username = '';
    public $smtp_password = '';
    public $smtp_encryption = 'tls';
    
    public $test_email = '';

    private SettingsService $settingsService;

    public function mount(): void
    {
        $this->settingsService = app(SettingsService::class);
        
        // Load current settings values
        $this->mail_from_address = $this->settingsService->get('mail_from_address', 'noreply@example.com');
        $this->mail_from_name = $this->settingsService->get('mail_from_name', 'Mudeer Bedaie');
        $this->smtp_host = $this->settingsService->get('smtp_host', '');
        $this->smtp_port = $this->settingsService->get('smtp_port', 587);
        $this->smtp_username = $this->settingsService->get('smtp_username', '');
        $this->smtp_password = $this->settingsService->get('smtp_password', '');
        $this->smtp_encryption = $this->settingsService->get('smtp_encryption', 'tls');
    }

    public function save(): void
    {
        $this->validate([
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'required|in:tls,ssl,none',
        ]);

        // Save email settings
        $this->settingsService->set('mail_from_address', $this->mail_from_address, 'string', 'email');
        $this->settingsService->set('mail_from_name', $this->mail_from_name, 'string', 'email');
        $this->settingsService->set('smtp_host', $this->smtp_host, 'string', 'email');
        $this->settingsService->set('smtp_port', $this->smtp_port, 'number', 'email');
        $this->settingsService->set('smtp_username', $this->smtp_username, 'encrypted', 'email');
        $this->settingsService->set('smtp_password', $this->smtp_password, 'encrypted', 'email');
        $this->settingsService->set('smtp_encryption', $this->smtp_encryption, 'string', 'email');

        $this->dispatch('settings-saved');
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'test_email' => 'required|email',
        ]);

        try {
            // Create a simple test email
            Mail::raw('This is a test email from your email settings configuration.', function ($message) {
                $message->to($this->test_email)
                        ->subject('Test Email from ' . ($this->mail_from_name ?: 'Mudeer Bedaie'))
                        ->from($this->mail_from_address, $this->mail_from_name);
            });

            $this->dispatch('email-test-success');
            $this->test_email = '';
        } catch (\Exception $e) {
            $this->dispatch('email-test-failed', message: $e->getMessage());
        }
    }

    public function getEncryptionOptions(): array
    {
        return [
            'tls' => 'TLS (recommended)',
            'ssl' => 'SSL',
            'none' => 'None (not recommended)',
        ];
    }

    public function getCommonPorts(): array
    {
        return [
            587 => '587 (TLS - recommended)',
            465 => '465 (SSL)',
            25 => '25 (unencrypted)',
            2525 => '2525 (alternative)',
        ];
    }

    public function layout(): string
    {
        return 'components.admin.settings-layout';
    }

    public function layoutData(): array
    {
        return [
            'title' => 'Email Settings',
            'activeTab' => 'email'
        ];
    }
}
?>

<div>
    <div class="space-y-6">
        <!-- Email Configuration -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Email Configuration</flux:heading>
            
            <form wire:submit="save" class="space-y-6">
                <!-- From Settings -->
                <div>
                    <flux:heading size="md" class="mb-4">From Address</flux:heading>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>From Email Address *</flux:label>
                            <flux:description>
                                The email address that will appear as the sender.
                            </flux:description>
                            <flux:input 
                                type="email"
                                wire:model="mail_from_address" 
                                placeholder="noreply@example.com"
                            />
                            <flux:error name="mail_from_address" />
                        </flux:field>

                        <flux:field>
                            <flux:label>From Name *</flux:label>
                            <flux:description>
                                The name that will appear as the sender.
                            </flux:description>
                            <flux:input 
                                wire:model="mail_from_name" 
                                placeholder="Mudeer Bedaie"
                            />
                            <flux:error name="mail_from_name" />
                        </flux:field>
                    </div>
                </div>

                <flux:separator />

                <!-- SMTP Settings -->
                <div>
                    <flux:heading size="md" class="mb-4">SMTP Server Settings</flux:heading>
                    <flux:description class="mb-6">
                        Configure your SMTP server for sending emails. Leave empty to use the default mail driver.
                    </flux:description>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <flux:field>
                            <flux:label>SMTP Host</flux:label>
                            <flux:description>
                                Your SMTP server hostname (e.g., smtp.gmail.com, smtp.mailgun.org)
                            </flux:description>
                            <flux:input 
                                wire:model="smtp_host" 
                                placeholder="smtp.gmail.com"
                            />
                            <flux:error name="smtp_host" />
                        </flux:field>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>SMTP Port *</flux:label>
                                <flux:select wire:model="smtp_port">
                                    @foreach($this->getCommonPorts() as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="smtp_port" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Encryption *</flux:label>
                                <flux:select wire:model="smtp_encryption">
                                    @foreach($this->getEncryptionOptions() as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="smtp_encryption" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>SMTP Username</flux:label>
                                <flux:description>
                                    Your SMTP username (usually your email address)
                                </flux:description>
                                <flux:input 
                                    wire:model="smtp_username" 
                                    placeholder="your-email@example.com"
                                />
                                <flux:error name="smtp_username" />
                            </flux:field>

                            <flux:field>
                                <flux:label>SMTP Password</flux:label>
                                <flux:description>
                                    Your SMTP password (will be encrypted when saved)
                                </flux:description>
                                <flux:input 
                                    type="password"
                                    wire:model="smtp_password" 
                                    placeholder="Enter password"
                                />
                                <flux:error name="smtp_password" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                <flux:separator />

                <!-- Save Button -->
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">
                        Save Email Settings
                    </flux:button>
                </div>
            </form>
        </flux:card>

        <!-- Test Email -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Test Email Configuration</flux:heading>
            <flux:description class="mb-6">
                Send a test email to verify your email configuration is working correctly.
            </flux:description>

            <form wire:submit="sendTestEmail" class="space-y-4">
                <flux:field>
                    <flux:label>Test Email Address</flux:label>
                    <div class="flex space-x-3">
                        <flux:input 
                            type="email"
                            wire:model="test_email" 
                            placeholder="test@example.com"
                            class="flex-1"
                        />
                        <flux:button type="submit" variant="outline">
                            Send Test Email
                        </flux:button>
                    </div>
                    <flux:error name="test_email" />
                </flux:field>
            </form>
        </flux:card>

        <!-- Email Configuration Tips -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Common Email Providers</flux:heading>
            
            <div class="space-y-4">
                <!-- Gmail -->
                <div class="border rounded-lg p-4 dark:border-gray-600">
                    <flux:heading size="sm" class="font-semibold mb-2">Gmail</flux:heading>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><strong>Host:</strong> smtp.gmail.com</div>
                        <div><strong>Port:</strong> 587</div>
                        <div><strong>Encryption:</strong> TLS</div>
                        <div><strong>Note:</strong> Use App Password instead of regular password</div>
                    </div>
                </div>

                <!-- Mailgun -->
                <div class="border rounded-lg p-4 dark:border-gray-600">
                    <flux:heading size="sm" class="font-semibold mb-2">Mailgun</flux:heading>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><strong>Host:</strong> smtp.mailgun.org</div>
                        <div><strong>Port:</strong> 587</div>
                        <div><strong>Encryption:</strong> TLS</div>
                        <div><strong>Username:</strong> Your Mailgun SMTP login</div>
                    </div>
                </div>

                <!-- SendGrid -->
                <div class="border rounded-lg p-4 dark:border-gray-600">
                    <flux:heading size="sm" class="font-semibold mb-2">SendGrid</flux:heading>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><strong>Host:</strong> smtp.sendgrid.net</div>
                        <div><strong>Port:</strong> 587</div>
                        <div><strong>Encryption:</strong> TLS</div>
                        <div><strong>Username:</strong> apikey</div>
                    </div>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Success/Error Messages -->
    <div 
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:settings-saved.window="show = true; message = 'Settings saved successfully!'; type = 'success'; setTimeout(() => show = false, 3000)"
        x-on:email-test-success.window="show = true; message = 'Test email sent successfully!'; type = 'success'; setTimeout(() => show = false, 3000)"
        x-on:email-test-failed.window="show = true; message = $event.detail.message || 'Failed to send test email!'; type = 'error'; setTimeout(() => show = false, 5000)"
        x-show="show"
        x-transition
        class="fixed top-4 right-4 z-50"
    >
        <flux:badge color="emerald" size="lg" x-show="type === 'success'">
            <flux:icon icon="check-circle" class="w-4 h-4 mr-2" />
            <span x-text="message"></span>
        </flux:badge>
        <flux:badge color="red" size="lg" x-show="type === 'error'">
            <flux:icon icon="exclamation-circle" class="w-4 h-4 mr-2" />
            <span x-text="message"></span>
        </flux:badge>
    </div>
</div>