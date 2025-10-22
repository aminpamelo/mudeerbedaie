<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Component;

new class extends Component {
    public $from_email = '';
    public $from_name = '';
    public $smtp_host = '';
    public $smtp_port = '587';
    public $smtp_username = '';
    public $smtp_password = '';
    public $smtp_encryption = 'tls';
    public $test_email = '';

    public function mount(): void
    {
        $this->from_email = Setting::where('key', 'email.from_address')->value('value') ?? '';
        $this->from_name = Setting::where('key', 'email.from_name')->value('value') ?? '';
        $this->smtp_host = Setting::where('key', 'email.smtp_host')->value('value') ?? '';
        $this->smtp_port = Setting::where('key', 'email.smtp_port')->value('value') ?? '587';
        $this->smtp_username = Setting::where('key', 'email.smtp_username')->value('value') ?? '';
        $this->smtp_password = Setting::where('key', 'email.smtp_password')->value('value') ?? '';
        $this->smtp_encryption = Setting::where('key', 'email.smtp_encryption')->value('value') ?? 'tls';
    }

    public function save(): void
    {
        $this->validate([
            'from_email' => 'required|email',
            'from_name' => 'required|string|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl',
        ]);

        $settings = [
            'email.from_address' => $this->from_email,
            'email.from_name' => $this->from_name,
            'email.smtp_host' => $this->smtp_host,
            'email.smtp_port' => $this->smtp_port,
            'email.smtp_username' => $this->smtp_username,
            'email.smtp_encryption' => $this->smtp_encryption,
        ];

        // Only update password if it's not empty
        if (!empty($this->smtp_password)) {
            $settings['email.smtp_password'] = $this->smtp_password;
        }

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => 'email',
                    'type' => $key === 'email.smtp_password' ? 'encrypted' : 'string',
                ]
            );
        }

        session()->flash('message', 'Email settings saved successfully.');
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'test_email' => 'required|email',
        ]);

        try {
            // Configure mail settings dynamically
            $this->configureMailDriver();

            Mail::raw('This is a test email from your broadcast system. If you received this, your email configuration is working correctly!', function ($message) {
                $message->to($this->test_email)
                    ->subject('Test Email Configuration');
            });

            session()->flash('message', 'Test email sent successfully to ' . $this->test_email);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }

    private function configureMailDriver(): void
    {
        if (!empty($this->smtp_host)) {
            config([
                'mail.mailers.smtp.host' => $this->smtp_host,
                'mail.mailers.smtp.port' => $this->smtp_port,
                'mail.mailers.smtp.username' => $this->smtp_username,
                'mail.mailers.smtp.password' => $this->smtp_password,
                'mail.mailers.smtp.encryption' => $this->smtp_encryption,
                'mail.from.address' => $this->from_email,
                'mail.from.name' => $this->from_name,
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Email Configuration</flux:heading>
            <flux:text class="mt-2">Configure your SMTP server settings for sending broadcast emails</flux:text>
        </div>
    </div>

    @if (session()->has('message'))
        <flux:callout variant="success" class="mb-6">
            {{ session('message') }}
        </flux:callout>
    @endif

    @if (session()->has('error'))
        <flux:callout variant="danger" class="mb-6">
            {{ session('error') }}
        </flux:callout>
    @endif

    <form wire:submit="save">
        <flux:card class="space-y-6">
            <div class="p-6 space-y-6">
                <!-- From Address Section -->
                <div>
                    <flux:heading size="lg" class="mb-4">From Address</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <flux:field>
                                <flux:label>From Email Address</flux:label>
                                <flux:text class="text-sm text-gray-600 mb-2">The email address that will appear as the sender.</flux:text>
                                <flux:input wire:model="from_email" type="email" placeholder="noreply@example.com" />
                                <flux:error name="from_email" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>From Name</flux:label>
                                <flux:text class="text-sm text-gray-600 mb-2">The name that will appear as the sender.</flux:text>
                                <flux:input wire:model="from_name" placeholder="Mudeer Bedaie" />
                                <flux:error name="from_name" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                <flux:separator />

                <!-- SMTP Server Settings -->
                <div>
                    <flux:heading size="lg" class="mb-2">SMTP Server Settings</flux:heading>
                    <flux:text class="text-sm text-gray-600 mb-4">Configure your SMTP server for sending emails. Leave empty to use the default mail driver.</flux:text>

                    <div class="space-y-4">
                        <div>
                            <flux:field>
                                <flux:label>SMTP Host</flux:label>
                                <flux:text class="text-sm text-gray-600 mb-2">Your SMTP server hostname (e.g., smtp.gmail.com, smtp.mailgun.org)</flux:text>
                                <flux:input wire:model="smtp_host" placeholder="smtp.gmail.com" />
                                <flux:error name="smtp_host" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <flux:field>
                                    <flux:label>SMTP Port</flux:label>
                                    <flux:select wire:model="smtp_port">
                                        <flux:select.option value="587">587 (TLS - recommended)</flux:select.option>
                                        <flux:select.option value="465">465 (SSL)</flux:select.option>
                                        <flux:select.option value="25">25 (No encryption)</flux:select.option>
                                    </flux:select>
                                    <flux:error name="smtp_port" />
                                </flux:field>
                            </div>

                            <div>
                                <flux:field>
                                    <flux:label>Encryption</flux:label>
                                    <flux:select wire:model="smtp_encryption">
                                        <flux:select.option value="tls">TLS (recommended)</flux:select.option>
                                        <flux:select.option value="ssl">SSL</flux:select.option>
                                    </flux:select>
                                    <flux:error name="smtp_encryption" />
                                </flux:field>
                            </div>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>SMTP Username</flux:label>
                                <flux:text class="text-sm text-gray-600 mb-2">Your SMTP username (usually your email address)</flux:text>
                                <flux:input wire:model="smtp_username" placeholder="your-email@example.com" />
                                <flux:error name="smtp_username" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>SMTP Password</flux:label>
                                <flux:text class="text-sm text-gray-600 mb-2">Your SMTP password (will be encrypted when saved)</flux:text>
                                <flux:input wire:model="smtp_password" type="password" placeholder="Enter password" />
                                <flux:error name="smtp_password" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                <flux:separator />

                <!-- Test Email Configuration -->
                <div>
                    <flux:heading size="lg" class="mb-2">Test Email Configuration</flux:heading>
                    <flux:text class="text-sm text-gray-600 mb-4">Send a test email to verify your email configuration is working correctly.</flux:text>

                    <div class="flex gap-4">
                        <div class="flex-1">
                            <flux:field>
                                <flux:label>Test Email Address</flux:label>
                                <flux:input wire:model="test_email" type="email" placeholder="test@example.com" />
                                <flux:error name="test_email" />
                            </flux:field>
                        </div>
                        <div class="flex items-end">
                            <flux:button type="button" variant="outline" wire:click="sendTestEmail">
                                Send Test Email
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                <flux:button type="submit" variant="primary">
                    Save Email Settings
                </flux:button>
            </div>
        </flux:card>
    </form>
</div>
