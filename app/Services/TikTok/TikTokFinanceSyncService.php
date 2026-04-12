<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\PlatformAccount;
use App\Models\TiktokFinanceStatement;
use App\Models\TiktokFinanceTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TikTokFinanceSyncService
{
    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService
    ) {}

    /**
     * Sync finance statements.
     */
    public function syncStatements(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        $response = $client->Finance->getStatements(['page_size' => 100]);

        $statements = $response['statements'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($statements as $statement) {
            $statementId = $statement['statement_id'] ?? $statement['id'] ?? null;

            if (! $statementId) {
                continue;
            }

            // Parse statement_time from unix timestamp
            $statementTime = isset($statement['statement_time'])
                ? Carbon::createFromTimestamp($statement['statement_time'])
                : null;

            TiktokFinanceStatement::updateOrCreate(
                [
                    'platform_account_id' => $account->id,
                    'tiktok_statement_id' => $statementId,
                ],
                [
                    'statement_type' => $statement['statement_type'] ?? $statement['type'] ?? null,
                    'total_amount' => $statement['total_amount'] ?? 0,
                    'order_amount' => $statement['order_amount'] ?? 0,
                    'commission_amount' => $statement['commission_amount'] ?? 0,
                    'shipping_fee' => $statement['shipping_fee'] ?? 0,
                    'platform_fee' => $statement['platform_fee'] ?? 0,
                    'currency' => $statement['currency'] ?? 'MYR',
                    'status' => $statement['status'] ?? null,
                    'statement_time' => $statementTime,
                    'raw_response' => $statement,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync transactions for a specific finance statement.
     */
    public function syncStatementTransactions(PlatformAccount $account, TiktokFinanceStatement $statement): int
    {
        $client = $this->getClient($account);

        $response = $client->Finance->getStatementTransactions($statement->tiktok_statement_id, ['page_size' => 100]);

        $transactions = $response['transactions'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($transactions as $transaction) {
            // Parse order_created_at from unix timestamp
            $orderCreatedAt = isset($transaction['order_created_at'])
                ? Carbon::createFromTimestamp($transaction['order_created_at'])
                : null;

            TiktokFinanceTransaction::create([
                'platform_account_id' => $account->id,
                'statement_id' => $statement->id,
                'tiktok_order_id' => $transaction['order_id'] ?? $transaction['tiktok_order_id'] ?? null,
                'transaction_type' => $transaction['transaction_type'] ?? $transaction['type'] ?? null,
                'order_amount' => $transaction['order_amount'] ?? 0,
                'seller_revenue' => $transaction['seller_revenue'] ?? 0,
                'affiliate_commission' => $transaction['affiliate_commission'] ?? 0,
                'platform_commission' => $transaction['platform_commission'] ?? 0,
                'shipping_fee' => $transaction['shipping_fee'] ?? 0,
                'order_created_at' => $orderCreatedAt,
                'raw_response' => $transaction,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Fetch payments (raw response).
     *
     * @return array<string, mixed>
     */
    public function fetchPayments(PlatformAccount $account): array
    {
        $client = $this->getClient($account);

        return $client->Finance->getPayments();
    }

    /**
     * Fetch withdrawals (raw response).
     *
     * @return array<string, mixed>
     */
    public function fetchWithdrawals(PlatformAccount $account): array
    {
        $client = $this->getClient($account);

        return $client->Finance->getWithdrawals();
    }

    /**
     * Fetch order transactions (raw response).
     *
     * @return array<string, mixed>
     */
    public function fetchOrderTransactions(PlatformAccount $account, string $orderId): array
    {
        $client = $this->getClient($account);

        return $client->Finance->getOrderTransactions($orderId);
    }

    /**
     * Get an authenticated client, refreshing the token if needed.
     */
    protected function getClient(PlatformAccount $account): mixed
    {
        if ($this->authService->needsTokenRefresh($account)) {
            Log::info('[TikTok Finance Sync] Refreshing token before sync', [
                'account_id' => $account->id,
            ]);

            $this->authService->refreshToken($account);
        }

        return $this->clientFactory->createClientForAccount($account);
    }
}
