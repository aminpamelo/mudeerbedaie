<?php

namespace App\Services\Funnel;

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Marketing API client for the Funnel Studio ads integration.
 * Each FacebookAdConnection holds one Business Manager + access token
 * (a System User token with ads_read is recommended — it doesn't expire).
 */
class FacebookAdsService
{
    protected const API_VERSION = 'v21.0';

    protected const BASE_URL = 'https://graph.facebook.com';

    /**
     * Verify the token and BM access; updates connection status.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyConnection(FacebookAdConnection $connection): array
    {
        try {
            $me = Http::timeout(20)->get($this->url('/me'), [
                'fields' => 'id,name',
                'access_token' => $connection->access_token,
            ]);

            if (! $me->successful()) {
                return $this->markError($connection, $this->graphError($me, 'Token rejected by Facebook.'));
            }

            $bm = Http::timeout(20)->get($this->url('/'.$connection->business_manager_id), [
                'fields' => 'id,name',
                'access_token' => $connection->access_token,
            ]);

            if (! $bm->successful()) {
                return $this->markError($connection, $this->graphError($bm, 'Token is valid but cannot access this Business Manager.'));
            }

            $bmName = $bm->json('name');
            $connection->update([
                'status' => 'connected',
                'status_message' => 'Connected as '.($me->json('name') ?? 'unknown').' → BM: '.($bmName ?? $connection->business_manager_id),
            ]);

            return ['success' => true, 'message' => "Connected to Business Manager \"{$bmName}\"."];
        } catch (\Exception $e) {
            return $this->markError($connection, 'Connection failed: '.$e->getMessage());
        }
    }

    /**
     * Pull every ad account (owned + client) the BM exposes.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function syncAdAccounts(FacebookAdConnection $connection): array
    {
        $count = 0;

        foreach (['owned_ad_accounts', 'client_ad_accounts'] as $edge) {
            $after = null;

            do {
                try {
                    $response = Http::timeout(30)->get($this->url("/{$connection->business_manager_id}/{$edge}"), array_filter([
                        'fields' => 'id,account_id,name,currency,account_status',
                        'limit' => 100,
                        'after' => $after,
                        'access_token' => $connection->access_token,
                    ]));
                } catch (\Exception $e) {
                    return ['success' => false, 'message' => 'Ad account sync failed: '.$e->getMessage()];
                }

                if (! $response->successful()) {
                    // client_ad_accounts may simply be unavailable — only fail hard on the primary edge.
                    if ($edge === 'owned_ad_accounts') {
                        return ['success' => false, 'message' => $this->graphError($response, 'Could not list ad accounts.')];
                    }

                    break;
                }

                foreach ($response->json('data', []) as $row) {
                    FacebookAdAccount::updateOrCreate(
                        [
                            'facebook_ad_connection_id' => $connection->id,
                            'account_id' => (string) ($row['account_id'] ?? ltrim($row['id'], 'act_')),
                        ],
                        [
                            'name' => $row['name'] ?? 'Unnamed account',
                            'currency' => $row['currency'] ?? null,
                            'account_status' => isset($row['account_status']) ? (string) $row['account_status'] : null,
                        ]
                    );
                    $count++;
                }

                $after = $response->json('paging.cursors.after');
                $hasNext = (bool) $response->json('paging.next');
            } while ($after && $hasNext);
        }

        return ['success' => true, 'message' => "Synced {$count} ad account(s).", 'count' => $count];
    }

    /**
     * Pull daily campaign-level insights for one ad account.
     *
     * @return array{success: bool, message: string, rows?: int}
     */
    public function syncInsights(FacebookAdAccount $account, int $days = 30): array
    {
        $connection = $account->connection;
        $since = now()->subDays($days)->toDateString();
        $until = now()->toDateString();
        $rows = 0;
        $after = null;

        do {
            try {
                $response = Http::timeout(60)->get($this->url("/act_{$account->account_id}/insights"), array_filter([
                    'level' => 'campaign',
                    'time_increment' => 1,
                    'fields' => 'campaign_id,campaign_name,spend,impressions,clicks,reach,cpm,cpc,ctr',
                    'time_range' => json_encode(['since' => $since, 'until' => $until]),
                    'limit' => 500,
                    'after' => $after,
                    'access_token' => $connection->access_token,
                ]));
            } catch (\Exception $e) {
                return ['success' => false, 'message' => "act_{$account->account_id}: ".$e->getMessage()];
            }

            if (! $response->successful()) {
                return ['success' => false, 'message' => $this->graphError($response, "Insights failed for act_{$account->account_id}.")];
            }

            foreach ($response->json('data', []) as $row) {
                FacebookAdInsight::updateOrCreate(
                    [
                        'facebook_ad_account_id' => $account->id,
                        'date' => $row['date_start'] ?? $since,
                        'campaign_id' => $row['campaign_id'] ?? null,
                    ],
                    [
                        'campaign_name' => $row['campaign_name'] ?? null,
                        'spend' => (float) ($row['spend'] ?? 0),
                        'impressions' => (int) ($row['impressions'] ?? 0),
                        'clicks' => (int) ($row['clicks'] ?? 0),
                        'reach' => isset($row['reach']) ? (int) $row['reach'] : null,
                        'cpm' => isset($row['cpm']) ? (float) $row['cpm'] : null,
                        'cpc' => isset($row['cpc']) ? (float) $row['cpc'] : null,
                        'ctr' => isset($row['ctr']) ? (float) $row['ctr'] : null,
                    ]
                );
                $rows++;
            }

            $after = $response->json('paging.cursors.after');
            $hasNext = (bool) $response->json('paging.next');
        } while ($after && $hasNext);

        return ['success' => true, 'message' => "Synced {$rows} insight row(s).", 'rows' => $rows];
    }

    /**
     * Full sync of one connection: verify, accounts, then insights per account.
     *
     * @return array{success: bool, message: string}
     */
    public function syncConnection(FacebookAdConnection $connection, int $days = 30): array
    {
        $verify = $this->verifyConnection($connection);
        if (! $verify['success']) {
            return $verify;
        }

        $accounts = $this->syncAdAccounts($connection);
        if (! $accounts['success']) {
            $this->markError($connection, $accounts['message']);

            return $accounts;
        }

        $errors = [];
        foreach ($connection->adAccounts()->get() as $account) {
            $result = $this->syncInsights($account, $days);
            if (! $result['success']) {
                $errors[] = $result['message'];
                Log::warning('Facebook Ads insight sync failed', [
                    'connection_id' => $connection->id,
                    'account_id' => $account->account_id,
                    'error' => $result['message'],
                ]);
            }
        }

        $connection->update(['last_synced_at' => now()]);

        return [
            'success' => true,
            'message' => empty($errors)
                ? 'Sync complete.'
                : 'Sync complete with some account errors: '.implode(' | ', array_slice($errors, 0, 3)),
        ];
    }

    protected function url(string $path): string
    {
        return self::BASE_URL.'/'.self::API_VERSION.$path;
    }

    protected function graphError($response, string $fallback): string
    {
        return $response->json('error.message') ?? $fallback;
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function markError(FacebookAdConnection $connection, string $message): array
    {
        $connection->update(['status' => 'error', 'status_message' => $message]);

        return ['success' => false, 'message' => $message];
    }
}
