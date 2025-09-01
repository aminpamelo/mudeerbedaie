<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'site_name',
                'value' => 'Mudeer Bedaie',
                'type' => 'string',
                'group' => 'general',
                'description' => 'The name of the website',
                'is_public' => true,
            ],
            [
                'key' => 'site_description',
                'value' => 'Educational Management System',
                'type' => 'text',
                'group' => 'general',
                'description' => 'Brief description of the website',
                'is_public' => true,
            ],
            [
                'key' => 'admin_email',
                'value' => 'admin@example.com',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Primary admin email address',
                'is_public' => false,
            ],
            [
                'key' => 'timezone',
                'value' => 'Asia/Kuala_Lumpur',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default timezone for the application',
                'is_public' => false,
            ],
            [
                'key' => 'language',
                'value' => 'en',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default language for the application',
                'is_public' => true,
            ],
            [
                'key' => 'date_format',
                'value' => 'd/m/Y',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default date format',
                'is_public' => true,
            ],
            [
                'key' => 'time_format',
                'value' => 'h:i A',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default time format',
                'is_public' => true,
            ],

            // Appearance Settings
            [
                'key' => 'logo_path',
                'value' => null,
                'type' => 'file',
                'group' => 'appearance',
                'description' => 'Website logo file path',
                'is_public' => true,
            ],
            [
                'key' => 'favicon_path',
                'value' => null,
                'type' => 'file',
                'group' => 'appearance',
                'description' => 'Website favicon file path',
                'is_public' => true,
            ],
            [
                'key' => 'primary_color',
                'value' => '#3B82F6',
                'type' => 'string',
                'group' => 'appearance',
                'description' => 'Primary brand color',
                'is_public' => true,
            ],
            [
                'key' => 'secondary_color',
                'value' => '#10B981',
                'type' => 'string',
                'group' => 'appearance',
                'description' => 'Secondary brand color',
                'is_public' => true,
            ],
            [
                'key' => 'footer_text',
                'value' => '© 2025 Mudeer Bedaie. All rights reserved.',
                'type' => 'text',
                'group' => 'appearance',
                'description' => 'Footer copyright text',
                'is_public' => true,
            ],

            // Payment Settings
            [
                'key' => 'stripe_publishable_key',
                'value' => null,
                'type' => 'encrypted',
                'group' => 'payment',
                'description' => 'Stripe publishable key',
                'is_public' => false,
            ],
            [
                'key' => 'stripe_secret_key',
                'value' => null,
                'type' => 'encrypted',
                'group' => 'payment',
                'description' => 'Stripe secret key',
                'is_public' => false,
            ],
            [
                'key' => 'stripe_webhook_secret',
                'value' => null,
                'type' => 'encrypted',
                'group' => 'payment',
                'description' => 'Stripe webhook secret key',
                'is_public' => false,
            ],
            [
                'key' => 'payment_mode',
                'value' => 'test',
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Payment mode (test or live)',
                'is_public' => false,
            ],
            [
                'key' => 'currency',
                'value' => 'MYR',
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Default currency for payments',
                'is_public' => true,
            ],
            [
                'key' => 'enable_stripe_payments',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment',
                'description' => 'Enable Stripe card payments',
                'is_public' => false,
            ],
            [
                'key' => 'enable_bank_transfers',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment',
                'description' => 'Enable manual bank transfers',
                'is_public' => false,
            ],
            [
                'key' => 'bank_name',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Bank name for transfers',
                'is_public' => false,
            ],
            [
                'key' => 'bank_account_name',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Bank account holder name',
                'is_public' => false,
            ],
            [
                'key' => 'bank_account_number',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Bank account number',
                'is_public' => false,
            ],
            [
                'key' => 'bank_swift_code',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Bank SWIFT/BIC code',
                'is_public' => false,
            ],

            // Email Settings
            [
                'key' => 'mail_from_address',
                'value' => 'noreply@example.com',
                'type' => 'string',
                'group' => 'email',
                'description' => 'Default from email address',
                'is_public' => false,
            ],
            [
                'key' => 'mail_from_name',
                'value' => 'Mudeer Bedaie',
                'type' => 'string',
                'group' => 'email',
                'description' => 'Default from name',
                'is_public' => false,
            ],
            [
                'key' => 'smtp_host',
                'value' => null,
                'type' => 'string',
                'group' => 'email',
                'description' => 'SMTP server host',
                'is_public' => false,
            ],
            [
                'key' => 'smtp_port',
                'value' => 587,
                'type' => 'number',
                'group' => 'email',
                'description' => 'SMTP server port',
                'is_public' => false,
            ],
            [
                'key' => 'smtp_username',
                'value' => null,
                'type' => 'encrypted',
                'group' => 'email',
                'description' => 'SMTP username',
                'is_public' => false,
            ],
            [
                'key' => 'smtp_password',
                'value' => null,
                'type' => 'encrypted',
                'group' => 'email',
                'description' => 'SMTP password',
                'is_public' => false,
            ],
            [
                'key' => 'smtp_encryption',
                'value' => 'tls',
                'type' => 'string',
                'group' => 'email',
                'description' => 'SMTP encryption method',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Settings seeded successfully!');
    }
}
