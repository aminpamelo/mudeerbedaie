<?php

namespace App\Console\Commands;

use App\Models\Platform;
use App\Models\PlatformApiCredential;
use Illuminate\Console\Command;

class TikTokFixCredentialExpiry extends Command
{
    protected $signature = 'tiktok:fix-credential-expiry';

    protected $description = 'Fix TikTok credentials with invalid expiry dates (e.g., year 2082)';

    public function handle(): int
    {
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        if (! $platform) {
            $this->error('TikTok Shop platform not found');

            return 1;
        }

        // Find credentials with expiry dates more than 2 years in the future (invalid)
        $twoYearsFromNow = now()->addYears(2);

        $invalidCredentials = PlatformApiCredential::where('platform_id', $platform->id)
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->where('expires_at', '>', $twoYearsFromNow)
            ->get();

        $this->info("Found {$invalidCredentials->count()} credentials with invalid expiry dates");

        foreach ($invalidCredentials as $credential) {
            $account = $credential->platformAccount;

            $this->line("Fixing credential for account: {$account->name}");
            $this->line("  - Old expires_at: {$credential->expires_at->toIso8601String()}");

            // Set to 24 hours from now (typical TikTok access token lifetime)
            $newExpiry = now()->addHours(24);
            $credential->expires_at = $newExpiry;
            $credential->save();

            $this->line("  - New expires_at: {$credential->expires_at->toIso8601String()}");
        }

        $this->info('Done!');

        return 0;
    }
}
