<?php

namespace App\Jobs;

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdCreative;
use App\Models\FacebookAdLevelInsight;
use App\Services\Funnel\Monitor\MetaAdLevelApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pulls the last few days of ad-level Meta insights for one ad account and replaces its
 * `facebook_ad_level_insights` rows for that window (Meta keeps revising recent days), then
 * refreshes running budgets, ad delivery statuses and new creatives. Unique per account so
 * the every-5-minutes schedule never stacks syncs; the outcome is stamped on the account.
 */
class SyncFacebookAdMonitor implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 900;

    /** Days per insights request; wider ad-level daily ranges make Meta fail. */
    private const WINDOW_DAYS = 7;

    public function __construct(
        public int $accountId,
        public int $days = 3,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function uniqueId(): string
    {
        return (string) $this->accountId;
    }

    public function handle(MetaAdLevelApi $api): void
    {
        $account = FacebookAdAccount::query()->with('connection')->find($this->accountId);

        if (! $account || blank($account->getRelationValue('connection')?->access_token)) {
            return;
        }

        $until = now()->startOfDay();
        $since = $until->copy()->subDays(max(1, $this->days) - 1);

        try {
            $rows = [];
            for ($from = $since->copy(); $from->lte($until); $from = $from->copy()->addDays(self::WINDOW_DAYS)) {
                $end = $from->copy()->addDays(self::WINDOW_DAYS - 1);
                array_push($rows, ...$api->fetch($account, $from, $end->gt($until) ? $until->copy() : $end));
            }

            $now = now();
            $records = [];
            foreach ($rows as $row) {
                if ($row['date'] !== '' && $row['ad_id'] !== '') {
                    $records[$row['date'].'|'.$row['ad_id']] = $row + ['facebook_ad_account_id' => $account->id, 'created_at' => $now, 'updated_at' => $now];
                }
            }

            DB::transaction(function () use ($account, $records, $since, $until) {
                FacebookAdLevelInsight::query()
                    ->where('facebook_ad_account_id', $account->id)
                    ->whereBetween('date', [$since->toDateString(), $until->toDateString()])
                    ->delete();

                foreach (array_chunk(array_values($records), 500) as $chunk) {
                    FacebookAdLevelInsight::query()->insert($chunk);
                }
            });
        } catch (Throwable $e) {
            $account->forceFill(['monitor_sync_error' => mb_substr($e->getMessage(), 0, 2000)])->save();

            if ($e instanceof RuntimeException) {
                $this->fail($e);

                return;
            }

            throw $e;
        }

        $account->forceFill(['monitor_synced_at' => now(), 'monitor_sync_error' => null])->save();

        try {
            $budget = $api->budgets($account);
            $account->forceFill([
                'daily_budget' => $budget['daily'],
                'lifetime_budgets' => $budget['lifetime'],
                'campaign_budgets' => $budget['campaigns'],
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Monitor budget sync failed', ['account' => $account->id, 'error' => $e->getMessage()]);
        }

        try {
            $ids = FacebookAdCreative::query()
                ->where('facebook_ad_account_id', $account->id)
                ->whereIn('ad_id', FacebookAdLevelInsight::query()
                    ->where('facebook_ad_account_id', $account->id)
                    ->where('date', '>=', now()->subDays(30)->toDateString())
                    ->distinct()->pluck('ad_id'))
                ->pluck('ad_id')->map(fn ($id) => (string) $id)->all();

            foreach ($api->adStatuses($account, array_values($ids)) as $adId => $status) {
                FacebookAdCreative::query()->where('ad_id', $adId)
                    ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', $status))
                    ->update(['status' => $status]);
            }
        } catch (Throwable $e) {
            Log::warning('Monitor ad status sync failed', ['account' => $account->id, 'error' => $e->getMessage()]);
        }

        SyncFacebookAdCreatives::dispatch($account->id);
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('Monitor ad-level sync failed.', ['account_id' => $this->accountId, 'error' => $e?->getMessage()]);
    }
}
