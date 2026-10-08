<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotClosingReference;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    $this->flowA = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'Qadha Solat', 'is_active' => true, 'use_ai' => true, 'match_type' => 'contains', 'trigger_keywords' => ['qadha']]);
    $this->flowB = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'Buku Haid', 'is_active' => true, 'use_ai' => true, 'match_type' => 'contains', 'trigger_keywords' => ['haid']]);
});

it('lists references with their flow names', function () {
    CekbotClosingReference::factory()->create(['title' => 'Closing Qadha', 'flow_ids' => [$this->flowA->id]]);

    test()->actingAs($this->admin)
        ->get(route('cekbot.references'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('References/Index', false)
            ->where('references.0.title', 'Closing Qadha')
            ->where('references.0.flow_names.0', 'Qadha Solat')
            ->has('flows', 2));
});

it('creates, updates, toggles and deletes a reference', function () {
    test()->actingAs($this->admin)
        ->post(route('cekbot.references.store'), [
            'title' => '  Closing ragu harga ',
            'transcript' => "Customer: mahal la\nSales: Faham kak...",
            'notes' => 'Tanya masalah dulu',
            'flow_ids' => [$this->flowA->id, $this->flowA->id],
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $ref = CekbotClosingReference::sole();
    expect($ref->title)->toBe('Closing ragu harga')
        ->and($ref->flow_ids)->toBe([$this->flowA->id])
        ->and($ref->created_by)->toBe($this->admin->id);

    test()->actingAs($this->admin)
        ->put(route('cekbot.references.update', $ref), ['title' => 'Baru', 'transcript' => 'x', 'flow_ids' => [], 'is_active' => true])
        ->assertSessionHasNoErrors();
    expect($ref->fresh()->flow_ids)->toBeNull()->and($ref->fresh()->isGlobal())->toBeTrue();

    test()->actingAs($this->admin)->put(route('cekbot.references.toggle', $ref))->assertRedirect();
    expect($ref->fresh()->is_active)->toBeFalse();

    test()->actingAs($this->admin)->delete(route('cekbot.references.destroy', $ref))->assertRedirect();
    expect(CekbotClosingReference::count())->toBe(0);
});

it('validates the reference form', function () {
    test()->actingAs($this->admin)
        ->post(route('cekbot.references.store'), ['title' => '', 'transcript' => '', 'flow_ids' => [99999]])
        ->assertSessionHasErrors(['title', 'transcript', 'flow_ids.0']);
});

it('blocks non-staff from the reference library', function () {
    test()->actingAs(User::factory()->create(['role' => 'student']))
        ->get(route('cekbot.references'))
        ->assertForbidden();
});

it('picks flow-tagged references first, then global, skipping inactive and other flows', function () {
    CekbotClosingReference::factory()->create(['title' => 'Global lama', 'flow_ids' => null]);
    CekbotClosingReference::factory()->create(['title' => 'Untuk Buku Haid', 'flow_ids' => [$this->flowB->id]]);
    CekbotClosingReference::factory()->create(['title' => 'Mati', 'flow_ids' => [$this->flowA->id], 'is_active' => false]);
    CekbotClosingReference::factory()->create(['title' => 'Untuk Qadha', 'flow_ids' => [$this->flowA->id]]);

    expect(CekbotClosingReference::forFlow($this->flowA->id)->pluck('title')->all())->toBe(['Untuk Qadha', 'Global lama'])
        ->and(CekbotClosingReference::forFlow(null)->pluck('title')->all())->toBe(['Global lama']);
});

it('masks phone numbers and emails before the AI sees a transcript', function () {
    CekbotClosingReference::factory()->create([
        'transcript' => "Customer (+60 12-345 6789): boleh call 0123456789 atau emel ali@gmail.com\nSales: Order RM49 x 2",
    ]);

    $prompt = CekbotClosingReference::promptFor(null);

    expect($prompt)->toContain('[nombor]')
        ->toContain('[emel]')
        ->toContain('RM49 x 2')
        ->not->toContain('0123456789')
        ->not->toContain('ali@gmail.com')
        ->not->toContain('345 6789');
});

it('feeds the references to the flow AI sales agent', function () {
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'k', 'openai.api_key' => 'sk-test']);
    Http::fake(['waha.test/*' => Http::response(['id' => 'out'], 201)]);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);
    CekbotFlowPackage::create(['cekbot_flow_id' => $this->flowA->id, 'label' => 'Pakej A', 'price' => 49, 'currency' => 'RM', 'sort_order' => 1]);
    CekbotClosingReference::factory()->create(['title' => 'Closing terbaik Mellissa', 'transcript' => 'Sales: Akak nak saya simpankan slot?', 'flow_ids' => [$this->flowA->id]]);

    OpenAI::fake([CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Baik kak 😊'], 'finish_reason' => 'stop']]])]);

    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'r1', 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'nak tanya kelas qadha', 'type' => 'chat', 'notifyName' => 'Ali'],
    ])->assertOk();

    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => $method === 'create'
        && collect($params['messages'])->contains(fn ($m) => ($m['role'] ?? '') === 'system'
            && str_contains((string) ($m['content'] ?? ''), 'RUJUKAN CLOSING')
            && str_contains((string) $m['content'], 'Closing terbaik Mellissa')));
});
