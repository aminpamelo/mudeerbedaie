<?php

declare(strict_types=1);

use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokAuthService;
use App\Services\TikTok\TikTokClientFactory;
use App\Services\TikTok\TikTokFinanceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeFinanceService(object $fakeClient): TikTokFinanceSyncService
{
    $authMock = Mockery::mock(TikTokAuthService::class);
    $factoryMock = Mockery::mock(TikTokClientFactory::class);

    return new class($factoryMock, $authMock, $fakeClient) extends TikTokFinanceSyncService
    {
        private object $testClient;

        public function __construct(
            TikTokClientFactory $clientFactory,
            TikTokAuthService $authService,
            object $testClient
        ) {
            parent::__construct($clientFactory, $authService);
            $this->testClient = $testClient;
        }

        protected function getClient(PlatformAccount $account): mixed
        {
            return $this->testClient;
        }
    };
}

it('syncs finance statements', function () {
    $account = PlatformAccount::factory()->create();

    $fakeFinance = new class
    {
        public function getStatements(array $params): array
        {
            return [
                'statements' => [
                    [
                        'statement_id' => 'stmt_001',
                        'statement_type' => 'SETTLEMENT',
                        'total_amount' => 5000.00,
                        'order_amount' => 4500.00,
                        'commission_amount' => 200.00,
                        'shipping_fee' => 150.00,
                        'platform_fee' => 150.00,
                        'currency' => 'MYR',
                        'status' => 'COMPLETED',
                        'statement_time' => 1700000000,
                    ],
                    [
                        'statement_id' => 'stmt_002',
                        'statement_type' => 'REFUND',
                        'total_amount' => 300.00,
                        'order_amount' => 280.00,
                        'commission_amount' => 10.00,
                        'shipping_fee' => 5.00,
                        'platform_fee' => 5.00,
                        'currency' => 'MYR',
                        'status' => 'PENDING',
                        'statement_time' => 1700100000,
                    ],
                ],
            ];
        }
    };

    $fakeClient = new class($fakeFinance)
    {
        public object $Finance;

        public function __construct(object $finance)
        {
            $this->Finance = $finance;
        }
    };

    $service = makeFinanceService($fakeClient);
    $count = $service->syncStatements($account);

    expect($count)->toBe(2);

    $this->assertDatabaseCount('tiktok_finance_statements', 2);
    $this->assertDatabaseHas('tiktok_finance_statements', [
        'platform_account_id' => $account->id,
        'tiktok_statement_id' => 'stmt_001',
        'statement_type' => 'SETTLEMENT',
        'currency' => 'MYR',
        'status' => 'COMPLETED',
    ]);
    $this->assertDatabaseHas('tiktok_finance_statements', [
        'platform_account_id' => $account->id,
        'tiktok_statement_id' => 'stmt_002',
        'statement_type' => 'REFUND',
        'currency' => 'MYR',
        'status' => 'PENDING',
    ]);
});
