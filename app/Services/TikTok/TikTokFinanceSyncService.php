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
        $count = 0;
        $pageToken = null;

        do {
            $params = ['page_size' => 100];
            if ($pageToken) {
                $params['page_token'] = $pageToken;
            }

            $response = $client->Finance->getStatements($params);
            $statements = $response['statements'] ?? $response['data'] ?? [];

            foreach ($statements as $statement) {
                $statementId = $statement['id'] ?? $statement['statement_id'] ?? null;

                if (! $statementId) {
                    continue;
                }

                $statementTime = isset($statement['statement_time'])
                    ? Carbon::createFromTimestamp((int) $statement['statement_time'])
                    : null;

                TiktokFinanceStatement::updateOrCreate(
                    [
                        'platform_account_id' => $account->id,
                        'tiktok_statement_id' => $statementId,
                    ],
                    [
                        'statement_type' => $statement['statement_type'] ?? $statement['type'] ?? null,
                        'total_amount' => $statement['settlement_amount'] ?? $statement['total_amount'] ?? 0,
                        'order_amount' => $statement['revenue_amount'] ?? $statement['order_amount'] ?? 0,
                        'commission_amount' => $statement['commission_amount'] ?? 0,
                        'shipping_fee' => $statement['shipping_cost_amount'] ?? $statement['shipping_fee'] ?? 0,
                        'platform_fee' => abs((float) ($statement['fee_amount'] ?? $statement['platform_fee'] ?? 0)),
                        'currency' => $statement['currency'] ?? 'MYR',
                        'status' => isset($statement['payment_status'])
                            ? strtolower($statement['payment_status'])
                            : ($statement['status'] ?? null),
                        'statement_time' => $statementTime,
                        'raw_response' => $statement,
                    ]
                );

                $count++;
            }

            $pageToken = $response['next_page_token'] ?? null;
        } while (! empty($pageToken));

        return $count;
    }

    /**
     * Sync transactions for a specific finance statement.
     */
    public function syncStatementTransactions(PlatformAccount $account, TiktokFinanceStatement $statement): int
    {
        $client = $this->getClient($account);
        $count = 0;
        $pageToken = null;

        // Replace this statement's transactions so re-syncs stay idempotent
        // (transactions table has no unique key on the TikTok transaction id).
        TiktokFinanceTransaction::where('statement_id', $statement->id)->delete();

        do {
            $params = ['page_size' => 100];
            if ($pageToken) {
                $params['page_token'] = $pageToken;
            }

            $response = $client->Finance->getStatementTransactions($statement->tiktok_statement_id, $params);
            $transactions = $response['statement_transactions'] ?? $response['transactions'] ?? $response['data'] ?? [];

            foreach ($transactions as $transaction) {
                $orderCreatedAt = isset($transaction['order_create_time'])
                    ? Carbon::createFromTimestamp((int) $transaction['order_create_time'])
                    : (isset($transaction['order_created_at'])
                        ? Carbon::createFromTimestamp((int) $transaction['order_created_at'])
                        : null);

                TiktokFinanceTransaction::create([
                    'platform_account_id' => $account->id,
                    'statement_id' => $statement->id,
                    'tiktok_order_id' => $transaction['order_id'] ?? $transaction['tiktok_order_id'] ?? null,
                    'transaction_type' => $transaction['type'] ?? $transaction['transaction_type'] ?? null,
                    'order_amount' => $transaction['revenue_amount'] ?? $transaction['order_amount'] ?? 0,
                    'seller_revenue' => $transaction['settlement_amount'] ?? $transaction['seller_revenue'] ?? 0,
                    'affiliate_commission' => abs((float) ($transaction['affiliate_commission_amount'] ?? $transaction['affiliate_commission'] ?? 0)),
                    'platform_commission' => abs((float) ($transaction['platform_commission_amount'] ?? $transaction['platform_commission'] ?? 0)),
                    'shipping_fee' => $transaction['shipping_cost_amount'] ?? $transaction['shipping_fee'] ?? 0,
                    'order_created_at' => $orderCreatedAt,
                    'raw_response' => $transaction,
                ]);

                $count++;
            }

            $pageToken = $response['next_page_token'] ?? null;
        } while (! empty($pageToken));

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
