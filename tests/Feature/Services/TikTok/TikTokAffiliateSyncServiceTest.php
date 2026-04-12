<?php

declare(strict_types=1);

use App\Models\PlatformAccount;
use App\Models\TiktokCreator;
use App\Services\TikTok\TikTokAffiliateSyncService;
use App\Services\TikTok\TikTokAuthService;
use App\Services\TikTok\TikTokClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAffiliateService(object $fakeClient): TikTokAffiliateSyncService
{
    $authMock = Mockery::mock(TikTokAuthService::class);
    $factoryMock = Mockery::mock(TikTokClientFactory::class);

    return new class($factoryMock, $authMock, $fakeClient) extends TikTokAffiliateSyncService
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

it('syncs creators from marketplace', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAffiliate = new class
    {
        public function searchCreatorOnMarketplace(array $params, array $filters): array
        {
            return [
                'creators' => [
                    [
                        'creator_user_id' => 'creator_abc',
                        'handle' => '@fashion_guru',
                        'display_name' => 'Fashion Guru',
                        'avatar_url' => 'https://example.com/avatar1.jpg',
                        'country_code' => 'MY',
                        'follower_count' => 50000,
                    ],
                    [
                        'creator_user_id' => 'creator_xyz',
                        'handle' => '@beauty_queen',
                        'display_name' => 'Beauty Queen',
                        'avatar_url' => 'https://example.com/avatar2.jpg',
                        'country_code' => 'SG',
                        'follower_count' => 120000,
                    ],
                ],
            ];
        }
    };

    $fakeClient = new class($fakeAffiliate)
    {
        public object $AffiliateSeller;

        public function __construct(object $affiliate)
        {
            $this->AffiliateSeller = $affiliate;
        }
    };

    $service = makeAffiliateService($fakeClient);
    $count = $service->syncCreators($account);

    expect($count)->toBe(2);

    $this->assertDatabaseCount('tiktok_creators', 2);
    $this->assertDatabaseHas('tiktok_creators', [
        'platform_account_id' => $account->id,
        'creator_user_id' => 'creator_abc',
        'handle' => '@fashion_guru',
        'display_name' => 'Fashion Guru',
        'country_code' => 'MY',
        'follower_count' => 50000,
    ]);
    $this->assertDatabaseHas('tiktok_creators', [
        'platform_account_id' => $account->id,
        'creator_user_id' => 'creator_xyz',
        'handle' => '@beauty_queen',
        'display_name' => 'Beauty Queen',
        'country_code' => 'SG',
        'follower_count' => 120000,
    ]);
});

it('syncs affiliate orders', function () {
    $account = PlatformAccount::factory()->create();

    // Pre-create a creator to test linking
    $creator = TiktokCreator::create([
        'platform_account_id' => $account->id,
        'creator_user_id' => 'creator_abc',
        'handle' => '@fashion_guru',
        'display_name' => 'Fashion Guru',
        'raw_response' => [],
    ]);

    $fakeAffiliate = new class
    {
        public function searchSellerAffiliateOrders(array $params): array
        {
            return [
                'orders' => [
                    [
                        'order_id' => 'aff_order_001',
                        'creator_user_id' => 'creator_abc',
                        'product_id' => 'prod_123',
                        'order_status' => 'COMPLETED',
                        'order_amount' => 150.00,
                        'commission_amount' => 15.00,
                        'commission_rate' => 0.10,
                        'collaboration_type' => 'OPEN',
                        'order_created_at' => 1700000000,
                    ],
                    [
                        'order_id' => 'aff_order_002',
                        'creator_user_id' => 'creator_unknown',
                        'product_id' => 'prod_456',
                        'order_status' => 'PENDING',
                        'order_amount' => 80.00,
                        'commission_amount' => 8.00,
                        'commission_rate' => 0.10,
                        'collaboration_type' => 'TARGET',
                        'order_created_at' => 1700100000,
                    ],
                ],
            ];
        }
    };

    $fakeClient = new class($fakeAffiliate)
    {
        public object $AffiliateSeller;

        public function __construct(object $affiliate)
        {
            $this->AffiliateSeller = $affiliate;
        }
    };

    $service = makeAffiliateService($fakeClient);
    $count = $service->syncAffiliateOrders($account);

    expect($count)->toBe(2);

    $this->assertDatabaseCount('tiktok_affiliate_orders', 2);

    // First order should be linked to the creator
    $this->assertDatabaseHas('tiktok_affiliate_orders', [
        'platform_account_id' => $account->id,
        'tiktok_order_id' => 'aff_order_001',
        'tiktok_creator_id' => $creator->id,
        'order_status' => 'COMPLETED',
        'collaboration_type' => 'OPEN',
    ]);

    // Second order should have no creator link (unknown creator)
    $this->assertDatabaseHas('tiktok_affiliate_orders', [
        'platform_account_id' => $account->id,
        'tiktok_order_id' => 'aff_order_002',
        'tiktok_creator_id' => null,
        'order_status' => 'PENDING',
        'collaboration_type' => 'TARGET',
    ]);
});

it('syncs creator collaboration content', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAffiliate = new class
    {
        public function getOpenCollaborationCreatorContentDetail(array $params): array
        {
            return [
                'contents' => [
                    [
                        'creator_user_id' => 'creator_new',
                        'creator_handle' => '@new_creator',
                        'creator_name' => 'New Creator',
                        'video_id' => 'vid_001',
                        'product_id' => 'prod_111',
                        'views' => 10000,
                        'likes' => 500,
                        'comments' => 50,
                        'shares' => 25,
                        'gmv' => 2500.00,
                        'orders' => 30,
                    ],
                ],
            ];
        }
    };

    $fakeClient = new class($fakeAffiliate)
    {
        public object $AffiliateSeller;

        public function __construct(object $affiliate)
        {
            $this->AffiliateSeller = $affiliate;
        }
    };

    $service = makeAffiliateService($fakeClient);
    $count = $service->syncCreatorContent($account);

    expect($count)->toBe(1);

    // A creator stub should have been auto-created
    $this->assertDatabaseHas('tiktok_creators', [
        'platform_account_id' => $account->id,
        'creator_user_id' => 'creator_new',
        'handle' => '@new_creator',
        'display_name' => 'New Creator',
    ]);

    $this->assertDatabaseHas('tiktok_creator_contents', [
        'platform_account_id' => $account->id,
        'creator_video_id' => 'vid_001',
        'tiktok_product_id' => 'prod_111',
        'views' => 10000,
        'likes' => 500,
        'comments' => 50,
        'shares' => 25,
        'orders' => 30,
    ]);
});
