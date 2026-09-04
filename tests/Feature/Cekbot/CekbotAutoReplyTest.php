<?php

use App\Models\CekbotAutoReply;
use App\Models\CekbotBotSetting;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key']);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'bot-out'], 201)]);
    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
});

function cekbotInbound(string $body, string $id, ?string $from = '60129999999@c.us'): array
{
    return [
        'event' => 'message',
        'session' => 'default',
        'payload' => ['id' => $id, 'from' => $from, 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => 'chat', 'notifyName' => 'Ali'],
    ];
}

function enableBot(CekbotSession $session, array $overrides = []): CekbotBotSetting
{
    return CekbotBotSetting::create(array_merge([
        'cekbot_session_id' => $session->id,
        'bot_enabled' => true,
    ], $overrides));
}

it('does not reply when the bot is disabled', function () {
    // no bot setting => disabled
    test()->postJson('/api/cekbot/webhook', cekbotInbound('harga berapa?', 'm1'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->count())->toBe(0);
});

it('auto-replies when a keyword rule matches', function () {
    enableBot($this->session);
    CekbotAutoReply::factory()->create([
        'cekbot_session_id' => $this->session->id,
        'keywords' => ['harga'],
        'reply_body' => 'Harga bermula RM99.',
    ]);

    test()->postJson('/api/cekbot/webhook', cekbotInbound('Nak tanya harga produk', 'm2'))->assertOk();

    $out = CekbotMessage::query()->where('direction', 'out')->first();
    expect($out)->not->toBeNull()
        ->and($out->body)->toBe('Harga bermula RM99.')
        ->and($out->from_me)->toBeTrue()
        ->and($out->sent_by_user_id)->toBeNull();
    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/sendText') && $r['text'] === 'Harga bermula RM99.');
});

it('sends the welcome message on the first inbound message', function () {
    enableBot($this->session, ['welcome_message' => 'Salam, selamat datang!']);

    test()->postJson('/api/cekbot/webhook', cekbotInbound('hi', 'm3'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->value('body'))->toBe('Salam, selamat datang!');
});

it('falls back to the default reply when nothing matches', function () {
    enableBot($this->session, ['default_reply' => 'Kami akan balas segera.']);
    // first message would trigger welcome (none set) -> so send two messages; second hits default
    test()->postJson('/api/cekbot/webhook', cekbotInbound('mesej satu', 'm4a'))->assertOk();
    test()->postJson('/api/cekbot/webhook', cekbotInbound('mesej dua', 'm4b'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->where('body', 'Kami akan balas segera.')->count())->toBeGreaterThanOrEqual(1);
});

it('does not reply in groups unless enabled', function () {
    enableBot($this->session, ['default_reply' => 'hello']);

    test()->postJson('/api/cekbot/webhook', cekbotInbound('hi group', 'm5', '120363000@g.us'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->count())->toBe(0);
});

it('admin can update bot settings', function () {
    test()->actingAs($this->admin)
        ->put("/admin/cekbot/auto-reply/{$this->session->id}/settings", [
            'bot_enabled' => true,
            'welcome_message' => 'Hai!',
        ])->assertRedirect();

    $this->assertDatabaseHas('cekbot_bot_settings', [
        'cekbot_session_id' => $this->session->id,
        'bot_enabled' => true,
        'welcome_message' => 'Hai!',
    ]);
});

it('admin can create an auto-reply rule', function () {
    test()->actingAs($this->admin)
        ->post("/admin/cekbot/auto-reply/{$this->session->id}/rules", [
            'name' => 'Salam',
            'match_type' => 'contains',
            'keywords' => ['salam', 'hi'],
            'reply_body' => 'Waalaikumsalam!',
        ])->assertRedirect();

    $this->assertDatabaseHas('cekbot_auto_replies', [
        'cekbot_session_id' => $this->session->id,
        'name' => 'Salam',
    ]);
});

it('sends the away message outside business hours', function () {
    Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-09-05 22:00:00')); // Saturday 10pm
    enableBot($this->session, [
        'welcome_message' => 'Hai!',
        'away_message' => 'Kami di luar waktu operasi. Balas esok. 🙏',
        'business_hours' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00', 'days' => [1, 2, 3, 4, 5]],
    ]);

    test()->postJson('/api/cekbot/webhook', cekbotInbound('hello', 'bh1'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->value('body'))->toBe('Kami di luar waktu operasi. Balas esok. 🙏');
    Illuminate\Support\Carbon::setTestNow();
});

it('replies normally inside business hours', function () {
    Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-09-07 10:00:00')); // Monday 10am
    enableBot($this->session, [
        'welcome_message' => 'Hai!',
        'away_message' => 'Tutup.',
        'business_hours' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00', 'days' => [1, 2, 3, 4, 5]],
    ]);

    test()->postJson('/api/cekbot/webhook', cekbotInbound('hello', 'bh2'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->value('body'))->toBe('Hai!');
    Illuminate\Support\Carbon::setTestNow();
});

it('renders the auto-reply page', function () {
    test()->actingAs($this->admin)
        ->get('/admin/cekbot/auto-reply')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('AutoReply/Index', false)->has('sessions'));
});
