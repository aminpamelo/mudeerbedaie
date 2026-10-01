<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotSession;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake(['graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200)]);

    $this->session = CekbotSession::factory()->create([
        'provider' => CekbotSession::PROVIDER_CLOUD_API,
        'phone_number_id' => 'PNID-ADS',
        'access_token' => 'EAAG-test-token',
        'phone_number' => '60177884209',
        'status' => CekbotSession::STATUS_WORKING,
    ]);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);

    $this->keywordFlow = adTestFlow($this->session, 'Buku 100 Amalan', ['trigger_keywords' => ['maklumat'], 'welcome_message' => 'Promo Buku 100 Amalan'], sortOrder: 0);
    $this->adFlow = adTestFlow($this->session, 'Tiga Surah', ['trigger_ads' => [['id' => '120211000000001', 'name' => 'Tiga Surah Pendek']], 'welcome_message' => 'Tiga Surah Pendek Jadi Pendinding'], sortOrder: 1);
});

function adTestFlow(CekbotSession $session, string $name, array $attributes, int $sortOrder): CekbotFlow
{
    $flow = CekbotFlow::create(array_merge([
        'cekbot_session_id' => $session->id,
        'name' => $name,
        'is_active' => true,
        'use_ai' => false,
        'match_type' => 'contains',
        'trigger_keywords' => [],
        'sort_order' => $sortOrder,
    ], $attributes));
    CekbotFlowPackage::create(['cekbot_flow_id' => $flow->id, 'label' => "{$name} Pakej", 'price' => 49, 'currency' => 'RM', 'sort_order' => 0]);

    return $flow;
}

/**
 * A Meta Cloud inbound text, optionally carrying a Click-to-WhatsApp `referral`.
 */
function adInbound(string $body, string $wamid, ?string $adId = null): void
{
    $message = ['from' => '60174874000', 'id' => $wamid, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $body]];

    if ($adId) {
        $message['referral'] = ['source_type' => 'ad', 'source_id' => $adId, 'source_url' => 'https://fb.me/x', 'headline' => 'Tiga Surah Pendek', 'ctwa_clid' => 'clid'];
    }

    test()->postJson('/api/cekbot/cloud/webhook', [
        'object' => 'whatsapp_business_account',
        'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '60177884209', 'phone_number_id' => 'PNID-ADS'],
            'contacts' => [['profile' => ['name' => 'Customer'], 'wa_id' => '60174874000']],
            'messages' => [$message],
        ]]]]],
    ])->assertOk();
}

function sentBodies(): array
{
    return Http::recorded()->map(fn ($pair) => $pair[0]['text']['body'] ?? null)->filter()->values()->all();
}

it('starts the ad flow from a Click-to-WhatsApp referral even when a keyword flow also matches', function () {
    adInbound('Helo! Boleh saya dapatkan maklumat lanjut tentang ini?', 'w1', '120211000000001');

    $enrollment = CekbotFlowEnrollment::query()->sole();
    expect($enrollment->cekbot_flow_id)->toBe($this->adFlow->id)
        ->and(collect(sentBodies())->contains(fn ($b) => str_contains($b, 'Tiga Surah Pendek Jadi Pendinding')))->toBeTrue()
        ->and(collect(sentBodies())->contains(fn ($b) => str_contains($b, 'Buku 100 Amalan')))->toBeFalse();
});

it('falls back to keywords for a message without an ad referral', function () {
    adInbound('Helo! Boleh saya dapatkan maklumat lanjut tentang ini?', 'w1');

    expect(CekbotFlowEnrollment::query()->sole()->cekbot_flow_id)->toBe($this->keywordFlow->id);
});

it('falls back to keywords when the ad is not linked to any flow', function () {
    adInbound('Nak maklumat', 'w1', '999999999999');

    expect(CekbotFlowEnrollment::query()->sole()->cekbot_flow_id)->toBe($this->keywordFlow->id);
});

it('switches a customer mid-funnel to the flow of a newly clicked ad', function () {
    adInbound('Nak maklumat', 'w1');
    adInbound('Helo! Boleh saya dapatkan maklumat lanjut tentang ini?', 'w2', '120211000000001');

    expect(CekbotFlowEnrollment::query()->where('cekbot_flow_id', $this->keywordFlow->id)->value('status'))->toBe(CekbotFlowEnrollment::STATUS_ABANDONED)
        ->and(CekbotFlowEnrollment::query()->where('status', CekbotFlowEnrollment::STATUS_ACTIVE)->sole()->cekbot_flow_id)->toBe($this->adFlow->id);
});

it('keeps the customer in the same flow when the same ad message arrives again', function () {
    adInbound('Helo', 'w1', '120211000000001');
    adInbound('Helo lagi', 'w2', '120211000000001');

    expect(CekbotFlowEnrollment::query()->count())->toBe(1);
});

it('ignores ad triggers on inactive flows', function () {
    $this->adFlow->update(['is_active' => false]);

    adInbound('Helo! Boleh saya dapatkan maklumat lanjut tentang ini?', 'w1', '120211000000001');

    expect(CekbotFlowEnrollment::query()->sole()->cekbot_flow_id)->toBe($this->keywordFlow->id);
});

it('reads the ad id only from ad referrals', function () {
    expect(CekbotFlow::adIdFromPayload(['referral' => ['source_type' => 'ad', 'source_id' => '123456']]))->toBe('123456')
        ->and(CekbotFlow::adIdFromPayload(['referral' => ['source_type' => 'post', 'source_id' => '123456']]))->toBeNull()
        ->and(CekbotFlow::adIdFromPayload(['text' => ['body' => 'hi']]))->toBeNull()
        ->and(CekbotFlow::adIdFromPayload(null))->toBeNull();
});

it('saves, de-duplicates and validates ad triggers in the builder', function () {
    $admin = User::factory()->admin()->create();
    $base = ['name' => 'Tiga Surah', 'match_type' => 'contains', 'trigger_keywords' => [], 'packages' => [['label' => 'Pakej', 'price' => 49]]];

    test()->actingAs($admin)
        ->put(route('cekbot.flows.update', $this->adFlow->id), $base + ['trigger_ads' => [
            ['id' => '120211000000002', 'name' => ' Iklan Baru '],
            ['id' => '120211000000002', 'name' => 'Dup'],
            ['id' => '120211000000003', 'name' => null],
        ]])
        ->assertSessionHasNoErrors();

    expect($this->adFlow->fresh()->trigger_ads)->toBe([
        ['id' => '120211000000002', 'name' => 'Iklan Baru'],
        ['id' => '120211000000003', 'name' => null],
    ]);

    test()->actingAs($admin)
        ->put(route('cekbot.flows.update', $this->adFlow->id), $base + ['trigger_ads' => [['id' => 'abc<script>']]])
        ->assertSessionHasErrors('trigger_ads.0.id');
});

it('searches ads across connected ad accounts for the picker', function () {
    $admin = User::factory()->admin()->create();
    $connection = FacebookAdConnection::create(['user_id' => $admin->id, 'name' => 'BM', 'business_manager_id' => '999', 'access_token' => 'tok', 'status' => 'connected']);
    FacebookAdAccount::create(['facebook_ad_connection_id' => $connection->id, 'account_id' => '111', 'name' => 'BeDaie Ads', 'account_status' => 1]);
    Http::fake(['graph.facebook.com/*/act_111/ads*' => Http::response(['data' => [
        ['id' => '120211000000009', 'name' => 'Tiga Surah Pendek', 'effective_status' => 'ACTIVE', 'creative' => ['thumbnail_url' => 'https://img/x.jpg']],
    ]])]);

    test()->actingAs($admin)
        ->getJson(route('cekbot.flows.ads-search', ['q' => 'surah']))
        ->assertOk()
        ->assertJsonPath('connected', true)
        ->assertJsonPath('ads.0.id', '120211000000009')
        ->assertJsonPath('ads.0.name', 'Tiga Surah Pendek')
        ->assertJsonPath('ads.0.account', 'BeDaie Ads');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'act_111/ads') && str_contains(urldecode($r->url()), '"value":"surah"'));
});

it('forbids non-admins from searching ads', function () {
    test()->actingAs(User::factory()->create())
        ->getJson(route('cekbot.flows.ads-search'))
        ->assertForbidden();
});
