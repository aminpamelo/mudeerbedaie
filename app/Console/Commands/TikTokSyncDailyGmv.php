<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokAnalyticsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class TikTokSyncDailyGmv extends Command
{
    protected $signature = 'tiktok:sync-daily-gmv
                            {--days=3 : How many days back to (re)fetch}
                            {--account= : Specific platform account ID}';

    protected $description = 'Sync per-day TikTok Shop GMV/orders (Seller Center Analytics) for every active shop';

    public function handle(TikTokAnalyticsSyncService $analytics): int
    {
        $accounts = $this->accounts();

        if ($accounts->isEmpty()) {
            $this->warn('No active TikTok Shop accounts found.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $failed = 0;

        foreach ($accounts as $account) {
            try {
                $stored = $analytics->syncShopDailyPerformance($account, $days);
                $this->line("{$account->name}: {$stored} day(s) stored");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("{$account->name}: {$e->getMessage()}");
                report($e);
            }
        }

        return $failed === $accounts->count() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, PlatformAccount>
     */
    private function accounts(): Collection
    {
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        if (! $platform) {
            return collect();
        }

        return PlatformAccount::query()
            ->where('platform_id', $platform->id)
            ->where('is_active', true)
            ->when($this->option('account'), fn ($q, $id) => $q->where('id', $id))
            ->get();
    }
}
