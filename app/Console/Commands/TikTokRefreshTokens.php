<?php

namespace App\Console\Commands;

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokAuthService;
use Illuminate\Console\Command;

class TikTokRefreshTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:refresh-tokens
                            {--account= : Specific account ID to refresh}
                            {--force : Force refresh even if not expiring soon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh TikTok Shop API tokens that are expiring soon';

    /**
     * Execute the console command.
     */
    public function handle(TikTokAuthService $authService): int
    {
        $this->info('Starting TikTok token refresh...');

        // Get TikTok platform
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        if (! $platform) {
            $this->error('TikTok Shop platform not found in database.');

            return Command::FAILURE;
        }

        // Get accounts to process
        $query = PlatformAccount::where('platform_id', $platform->id)
            ->where('is_active', true);

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('No active TikTok Shop accounts found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$accounts->count()} TikTok Shop account(s) to check.");

        $refreshed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($accounts as $account) {
            $this->line("Processing: {$account->name}");

            // Check if refresh is needed
            $needsRefresh = $this->option('force') || $authService->needsTokenRefresh($account);

            if (! $needsRefresh) {
                $this->line('  ↳ Token not expiring soon, skipping.');
                $skipped++;

                continue;
            }

            // Attempt refresh
            $success = $authService->refreshToken($account);

            if ($success) {
                $this->info('  ↳ Token refreshed successfully.');
                $refreshed++;
            } else {
                $this->error('  ↳ Failed to refresh token.');
                $failed++;
            }
        }

        // Summary
        $this->newLine();
        $this->info('Token refresh completed:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Refreshed', $refreshed],
                ['Failed', $failed],
                ['Skipped', $skipped],
            ]
        );

        if ($failed > 0) {
            $this->warn('Some accounts failed to refresh. Check the logs for details.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
