<?php

use App\Jobs\SendCekbotBroadcastJob;
use App\Models\CekbotBroadcast;
use App\Models\CekbotConversation;
use App\Models\CekbotSession;
use App\Models\User;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'cekbot.broadcast_throttle_seconds' => 0,
    ]);
    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
});

it('renders the broadcast page', function () {
    $this->actingAs($this->admin)
        ->get('/admin/cekbot/broadcast')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Broadcast/Index', false)->has('broadcasts'));
});

it('creates a broadcast, snapshots recipients (excluding groups), and dispatches the job', function () {
    Bus::fake();
    CekbotConversation::factory()->count(2)->create(['cekbot_session_id' => $this->session->id, 'is_group' => false]);
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => true]);

    $this->actingAs($this->admin)
        ->post('/admin/cekbot/broadcast', [
            'cekbot_session_id' => $this->session->id,
            'name' => 'Promo', 'message' => 'Salam {name}!',
            'audience_type' => 'all', 'include_groups' => false,
        ])->assertRedirect();

    $broadcast = CekbotBroadcast::first();
    expect($broadcast->total_recipients)->toBe(2)
        ->and($broadcast->recipients()->count())->toBe(2);
    Bus::assertDispatched(SendCekbotBroadcastJob::class);
});

it('filters recipients by label', function () {
    Bus::fake();
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'labels' => ['penting']]);
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'labels' => ['selesai']]);

    $this->actingAs($this->admin)
        ->post('/admin/cekbot/broadcast', [
            'cekbot_session_id' => $this->session->id, 'name' => 'X', 'message' => 'hi',
            'audience_type' => 'label', 'audience_value' => 'penting',
        ])->assertRedirect();

    expect(CekbotBroadcast::first()->total_recipients)->toBe(1);
});

it('schedules a broadcast without dispatching immediately', function () {
    Bus::fake();
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false]);

    $this->actingAs($this->admin)
        ->post('/admin/cekbot/broadcast', [
            'cekbot_session_id' => $this->session->id, 'name' => 'X', 'message' => 'hi',
            'audience_type' => 'all', 'scheduled_at' => now()->addHour()->toDateTimeString(),
        ])->assertRedirect();

    expect(CekbotBroadcast::first()->status)->toBe('scheduled');
    Bus::assertNotDispatched(SendCekbotBroadcastJob::class);
});

it('sends the broadcast to recipients with {name} personalisation', function () {
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'bc-out'], 201)]);
    $conv = CekbotConversation::factory()->create([
        'cekbot_session_id' => $this->session->id, 'is_group' => false, 'chat_id' => '60127@c.us', 'name' => 'Ali',
    ]);
    $broadcast = CekbotBroadcast::create([
        'cekbot_session_id' => $this->session->id, 'name' => 'X', 'message' => 'Salam {name}!',
        'audience_type' => 'all', 'status' => 'sending', 'total_recipients' => 1,
    ]);
    $broadcast->recipients()->create(['cekbot_conversation_id' => $conv->id, 'status' => 'pending']);

    (new SendCekbotBroadcastJob($broadcast->id))->handle(app(WahaSessionManager::class));

    expect($broadcast->fresh()->status)->toBe('sent')
        ->and($broadcast->fresh()->sent_count)->toBe(1);
    $this->assertDatabaseHas('cekbot_messages', ['cekbot_conversation_id' => $conv->id, 'direction' => 'out', 'body' => 'Salam Ali!']);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/sendText') && $r['text'] === 'Salam Ali!');
});

it('dispatches due scheduled broadcasts via the command', function () {
    Bus::fake();
    $broadcast = CekbotBroadcast::create([
        'cekbot_session_id' => $this->session->id, 'name' => 'X', 'message' => 'hi',
        'audience_type' => 'all', 'status' => 'scheduled', 'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('cekbot:run-broadcasts')->assertSuccessful();

    expect($broadcast->fresh()->status)->toBe('sending');
    Bus::assertDispatched(SendCekbotBroadcastJob::class);
});

it('counts recipients for the compose preview', function () {
    CekbotConversation::factory()->count(3)->create(['cekbot_session_id' => $this->session->id, 'is_group' => false]);

    $this->actingAs($this->admin)
        ->getJson("/admin/cekbot/broadcast-recipients-count?cekbot_session_id={$this->session->id}&audience_type=all")
        ->assertOk()
        ->assertJson(['count' => 3]);
});
