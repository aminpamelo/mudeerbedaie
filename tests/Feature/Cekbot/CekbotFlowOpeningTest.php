<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

beforeEach(function () {
    Storage::fake('public');
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'openai.api_key' => 'sk-test',
    ]);
    Http::fake([
        'waha.test/api/sendText' => Http::response(['id' => 'out'], 201),
        'waha.test/api/sendImage' => Http::response(['id' => 'img'], 201),
    ]);

    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);

    $this->flow = CekbotFlow::create([
        'cekbot_session_id' => $this->session->id,
        'name' => 'Funnel Qadha',
        'is_active' => true,
        'use_ai' => true,
        'match_type' => 'contains',
        'trigger_keywords' => ['berminat'],
        'payment_transfer_enabled' => true,
        'payment_cod_enabled' => true,
    ]);
    CekbotFlowPackage::create(['cekbot_flow_id' => $this->flow->id, 'label' => 'Buku Qadha', 'price' => 39, 'currency' => 'RM', 'sort_order' => 1]);
});

function openingInbound(string $body, string $id): void
{
    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => $id, 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => 'chat', 'notifyName' => 'Ali'],
    ])->assertOk();
}

function saveFlow(CekbotFlow $flow, array $opening)
{
    return test()->actingAs(test()->admin)->put(route('cekbot.flows.update', $flow->id), [
        'name' => $flow->name,
        'is_active' => true,
        'use_ai' => $flow->use_ai,
        'match_type' => 'contains',
        'trigger_keywords' => ['berminat'],
        'opening_messages' => $opening,
        'packages' => [['label' => 'Buku Qadha', 'price' => 39, 'currency' => 'RM']],
    ]);
}

it('uploads an opening image into the flow folder', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('cekbot.flows.opening-image.store', $this->flow->id), ['image' => UploadedFile::fake()->image('cover.jpg')])
        ->assertOk()
        ->assertJsonStructure(['path', 'url']);

    expect($response->json('path'))->toStartWith("cekbot-flows/opening/{$this->flow->id}/");
    Storage::disk('public')->assertExists($response->json('path'));
});

it('saves the opening sequence and shows it in the builder', function () {
    $path = UploadedFile::fake()->image('cover.jpg')->store("cekbot-flows/opening/{$this->flow->id}", 'public');

    saveFlow($this->flow, [
        ['type' => 'text', 'text' => '  Assalamualaikum! 🌙  ', 'path' => '', 'caption' => '', 'url' => 'ignored'],
        ['type' => 'image', 'text' => '', 'path' => $path, 'caption' => 'Buku Panduan Qadha Solat'],
    ])->assertSessionHasNoErrors();

    expect($this->flow->fresh()->opening_messages)->toBe([
        ['type' => 'text', 'text' => 'Assalamualaikum! 🌙'],
        ['type' => 'image', 'path' => $path, 'caption' => 'Buku Panduan Qadha Solat'],
    ]);

    $this->actingAs($this->admin)
        ->get(route('cekbot.flows.show', $this->flow->id))
        ->assertInertia(fn ($page) => $page
            ->where('flow.opening_messages.0.text', 'Assalamualaikum! 🌙')
            ->where('flow.opening_messages.1.caption', 'Buku Panduan Qadha Solat')
            ->where('flow.opening_messages.1.url', Storage::disk('public')->url($path)));
});

it('rejects opening image paths outside this flow folder', function (string $path) {
    saveFlow($this->flow, [['type' => 'image', 'path' => $path, 'caption' => '']])
        ->assertSessionHasErrors('opening_messages.0.path');
})->with([
    'other folder' => ['cekbot-flows/qr.png'],
    'other flow' => ['cekbot-flows/opening/999/x.jpg'],
    'traversal' => ['cekbot-flows/opening/1/../../../.env'],
]);

it('requires text for a text opening message', function () {
    saveFlow($this->flow, [['type' => 'text', 'text' => '']])
        ->assertSessionHasErrors('opening_messages.0.text');
});

it('deletes images removed from the opening sequence', function () {
    $keep = UploadedFile::fake()->image('a.jpg')->store("cekbot-flows/opening/{$this->flow->id}", 'public');
    $drop = UploadedFile::fake()->image('b.jpg')->store("cekbot-flows/opening/{$this->flow->id}", 'public');
    $this->flow->update(['opening_messages' => [
        ['type' => 'image', 'path' => $keep, 'caption' => null],
        ['type' => 'image', 'path' => $drop, 'caption' => null],
    ]]);

    saveFlow($this->flow, [['type' => 'image', 'path' => $keep, 'caption' => '']])->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists($keep);
    Storage::disk('public')->assertMissing($drop);
});

it('sends the opening sequence verbatim, then lets the AI take over on the next message', function () {
    $path = UploadedFile::fake()->image('cover.jpg')->store("cekbot-flows/opening/{$this->flow->id}", 'public');
    $this->flow->update(['opening_messages' => [
        ['type' => 'text', 'text' => 'Assalamualaikum! Terima kasih berminat 🌙'],
        ['type' => 'image', 'path' => $path, 'caption' => 'Buku Panduan Qadha Solat'],
        ['type' => 'text', 'text' => 'Nak saya terangkan isi kandungan buku ni?'],
    ]]);

    OpenAI::fake([
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Boleh! Buku ni ada 3 bab…'],
            'finish_reason' => 'stop',
        ]]]),
    ]);

    openingInbound('Saya berminat dengan buku ni', 'o1');

    $out = CekbotMessage::query()->where('direction', 'out')->orderBy('id')->get();
    expect($out)->toHaveCount(3)
        ->and($out[0]->body)->toBe('Assalamualaikum! Terima kasih berminat 🌙')
        ->and($out[1]->type)->toBe('image')
        ->and($out[1]->media_url)->toContain($path)
        ->and($out[1]->body)->toBe('Buku Panduan Qadha Solat')
        ->and($out[2]->body)->toBe('Nak saya terangkan isi kandungan buku ni?');

    // The AI didn't answer the trigger — the opening did.
    OpenAI::assertNothingSent();
    expect(CekbotFlowEnrollment::query()->latest('id')->first()->current_step)->toBe(CekbotFlowEnrollment::STEP_AI);

    openingInbound('ya boleh', 'o2');

    expect(CekbotMessage::query()->where('direction', 'out')->latest('id')->value('body'))->toBe('Boleh! Buku ni ada 3 bab…');
    // The AI sees what the opening said.
    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => collect($params['messages'])
        ->contains(fn ($m) => ($m['role'] ?? '') === 'assistant' && str_contains((string) $m['content'], 'Buku Panduan Qadha Solat')));
});

it('sends the opening before the package menu in menu mode', function () {
    $this->flow->update([
        'use_ai' => false,
        'opening_messages' => [['type' => 'text', 'text' => 'Assalamualaikum! 🌙']],
    ]);

    openingInbound('saya berminat', 'm1');

    $out = CekbotMessage::query()->where('direction', 'out')->orderBy('id')->pluck('body');
    expect($out)->toHaveCount(2)
        ->and($out[0])->toBe('Assalamualaikum! 🌙')
        ->and($out[1])->toContain('Buku Qadha');
});

it('behaves as before when no opening is set', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Salam! Ada Buku Qadha RM39.'],
            'finish_reason' => 'stop',
        ]]]),
    ]);

    openingInbound('saya berminat', 'n1');

    expect(CekbotMessage::query()->where('direction', 'out')->pluck('body')->all())->toBe(['Salam! Ada Buku Qadha RM39.']);
});
