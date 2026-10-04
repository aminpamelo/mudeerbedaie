<?php

declare(strict_types=1);

namespace App\Services\Funnel;

use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;

/**
 * Creates and edits Facebook Business Manager connections with the verify /
 * roll-back rules shared by the Funnel Studio (company BMs) and the Fighter
 * portal (a fighter's own BMs), so both surfaces behave identically.
 */
class FacebookAdConnectionManager
{
    public function __construct(
        protected FacebookAdsService $adsService
    ) {}

    /**
     * Create a connection, verify it with Facebook and pull its ad accounts.
     * A connection that fails verification is deleted so a retry stays clean.
     *
     * @param  array{name: string, business_manager_id: string, access_token: string}  $data
     * @return array{success: bool, message: string, connection: ?FacebookAdConnection, accounts_count: int}
     */
    public function connect(array $data, ?int $userId = null): array
    {
        $connection = FacebookAdConnection::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'business_manager_id' => preg_replace('/\s+/', '', $data['business_manager_id']),
            'access_token' => trim($data['access_token']),
        ]);

        $verify = $this->adsService->verifyConnection($connection);

        if (! $verify['success']) {
            $connection->delete();

            return ['success' => false, 'message' => $verify['message'], 'connection' => null, 'accounts_count' => 0];
        }

        $accounts = $this->adsService->syncAdAccounts($connection);

        return [
            'success' => true,
            'message' => $verify['message'],
            'connection' => $connection,
            'accounts_count' => $accounts['count'] ?? 0,
        ];
    }

    /**
     * Edit a connection's name, Business Manager ID and/or access token. The
     * name is applied as-is; changing the BM ID or pasting a new token
     * re-verifies with Facebook. A failed verify rolls the credentials back
     * so a previously-working connection is never broken by a bad edit.
     *
     * @param  array{name: string, business_manager_id: string, access_token?: ?string}  $data
     * @return array{success: bool, message: string, accounts_count: int}
     */
    public function update(FacebookAdConnection $connection, array $data): array
    {
        $newBmId = preg_replace('/\s+/', '', $data['business_manager_id']);
        $bmChanged = $newBmId !== $connection->business_manager_id;
        $tokenProvided = filled($data['access_token'] ?? null);

        if (! $bmChanged && ! $tokenProvided) {
            $connection->update(['name' => $data['name']]);

            return [
                'success' => true,
                'message' => 'Connection updated.',
                'accounts_count' => $connection->adAccounts()->count(),
            ];
        }

        $previous = [
            'business_manager_id' => $connection->business_manager_id,
            'access_token' => $connection->access_token,
            'status' => $connection->status,
            'status_message' => $connection->status_message,
        ];

        $attributes = [
            'name' => $data['name'],
            'business_manager_id' => $newBmId,
        ];
        if ($tokenProvided) {
            $attributes['access_token'] = trim($data['access_token']);
        }
        $connection->update($attributes);

        $verify = $this->adsService->verifyConnection($connection);

        if (! $verify['success']) {
            $connection->update($previous + ['name' => $data['name']]);

            return ['success' => false, 'message' => $verify['message'], 'accounts_count' => 0];
        }

        // A different BM means the old ad accounts (and their insights) belong
        // elsewhere — clear them before pulling the new BM's accounts.
        if ($bmChanged) {
            $oldAccountIds = $connection->adAccounts()->pluck('id');
            FacebookAdInsight::whereIn('facebook_ad_account_id', $oldAccountIds)->delete();
            $connection->adAccounts()->delete();
        }

        $accounts = $this->adsService->syncAdAccounts($connection);

        return [
            'success' => true,
            'message' => $verify['message'],
            'accounts_count' => $accounts['count'] ?? 0,
        ];
    }
}
