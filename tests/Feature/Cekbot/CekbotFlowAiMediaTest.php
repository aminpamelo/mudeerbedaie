<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotMedia;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;

beforeEach(function () {
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key', 'openai.api_key' => 'sk-test']);
    Http::fake(['waha.test/*' => Http::response(['id' => 'out-1'], 201)]);

    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);
    $this->flow = CekbotFlow::create([
        'cekbot_session_id' => $this->session->id,
        'name' => 'Qadha',
        'is_active' => true,
        'use_ai' => true,
        'match_type' => 'contains',
        'trigger_keywords' => ['qadha'],
        'ai_instructions' => 'Lepas terangkan pakej, hantar testimoni-1.',
    ]);
    CekbotFlowPackage::create(['cekbot_flow_id' => $this->flow->id, 'label' => 'Pakej 1', 'price' => 49, 'currency' => 'RM', 'sort_order' => 0]);
});

function mediaToolTurn(string $key, string $reply): void
{
    OpenAI::fake([
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'send_media', 'arguments' => json_encode(['key' => $key])],
            ]]],
            'finish_reason' => 'tool_calls',
        ]]]),
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $reply],
            'finish_reason' => 'stop',
        ]]]),
    ]);
}

function mediaInbound(string $body): void
{
    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'in-'.uniqid(), 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => 'chat', 'notifyName' => 'Ali'],
    ])->assertOk();
}

it('sends a library image after the AI text when the AI calls send_media', function () {
    $media = CekbotMedia::factory()->create(['key' => 'testimoni-1']);
    mediaToolTurn('testimoni-1', 'Ni testimoni pelanggan kami 👇');

    mediaInbound('nak tahu pasal qadha');

    $out = CekbotMessage::query()->where('direction', 'out')->orderBy('id')->get();
    expect($out->pluck('type')->all())->toBe(['text', 'image'])
        ->and($out[0]->body)->toBe('Ni testimoni pelanggan kami 👇')
        ->and($out[1]->media_url)->toBe($media->url());

    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/sendImage') && $r['file']['url'] === $media->url());
});

it('sends a library video through the video endpoint', function () {
    $media = CekbotMedia::factory()->video()->create(['key' => 'testimoni-1']);
    mediaToolTurn('testimoni-1', 'Tengok video ni 👇');

    mediaInbound('qadha');

    expect(CekbotMessage::query()->where('direction', 'out')->latest('id')->first()->only(['type', 'media_url']))
        ->toBe(['type' => 'video', 'media_url' => $media->url()]);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/sendVideo') && $r['file']['url'] === $media->url() && $r['file']['mimetype'] === 'video/mp4');
});

it('sends nothing extra for an unknown media key', function () {
    CekbotMedia::factory()->create(['key' => 'testimoni-1']);
    mediaToolTurn('tak-wujud', 'Maaf ye');

    mediaInbound('qadha');

    expect(CekbotMessage::query()->where('direction', 'out')->pluck('type')->all())->toBe(['text']);
});

it('offers the send_media tool and lists the library only when media exists', function () {
    OpenAI::fake([CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Hai'], 'finish_reason' => 'stop']]])]);
    mediaInbound('qadha');

    OpenAI::assertSent(Chat::class, fn (string $method, array $params) => ! collect($params['tools'])->contains(fn ($t) => data_get($t, 'function.name') === 'send_media'));

    CekbotMedia::factory()->video()->create(['key' => 'testimoni-1', 'title' => 'Video Puan Aminah']);
    \App\Models\CekbotFlowEnrollment::query()->update(['status' => 'abandoned']);
    OpenAI::fake([CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Hai'], 'finish_reason' => 'stop']]])]);
    mediaInbound('qadha lagi');

    OpenAI::assertSent(Chat::class, function (string $method, array $params) {
        $tool = collect($params['tools'])->first(fn ($t) => data_get($t, 'function.name') === 'send_media');
        $system = collect($params['messages'])->firstWhere('role', 'system')['content'] ?? '';

        return $tool
            && data_get($tool, 'function.parameters.properties.key.enum') === ['testimoni-1']
            && str_contains($system, 'testimoni-1 (video): Video Puan Aminah');
    });
});

it('sends a video to a Meta Cloud number as a video message', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.VID']]], 200)]);
    $cloud = CekbotSession::factory()->create([
        'provider' => CekbotSession::PROVIDER_CLOUD_API,
        'phone_number_id' => 'PNID-VID',
        'access_token' => 'EAAG-token',
        'status' => CekbotSession::STATUS_WORKING,
    ]);

    $result = app(\App\Services\Cekbot\CekbotOutbound::class)->sendVideo($cloud, '60129999999', 'https://cdn.test/v.mp4');

    expect($result['success'])->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'PNID-VID/messages')
        && $r['type'] === 'video'
        && $r['video']['link'] === 'https://cdn.test/v.mp4');
});

it('does not offer library files WhatsApp cannot send', function () {
    CekbotMedia::factory()->create([
        'key' => 'video-mov',
        'media_id' => \App\Models\Media::factory()->video()->state(['mime_type' => 'video/quicktime', 'file_size' => 1_000_000]),
    ]);
    OpenAI::fake([CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Hai'], 'finish_reason' => 'stop']]])]);

    mediaInbound('qadha');

    OpenAI::assertSent(Chat::class, fn (string $method, array $params) => ! collect($params['tools'])->contains(fn ($t) => data_get($t, 'function.name') === 'send_media')
        && ! str_contains(collect($params['messages'])->firstWhere('role', 'system')['content'] ?? '', 'video-mov'));
});

function mediaAiSays(string $content): void
{
    OpenAI::fake([CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']]])]);
}

function outboundTrail(): array
{
    return CekbotMessage::query()->where('direction', 'out')->orderBy('id')->get()
        ->map(fn ($m) => $m->type === 'text' ? $m->body : "[{$m->type}]")->all();
}

it('splits the reply into bubbles and sends "hantar key" media in place (production script)', function () {
    CekbotMedia::factory()->create(['key' => 'qadhausp']);
    mediaAiSays("Kalau macam tu, ambil Pakej 1 pun dah okay, cik.\n[[SPLIT]]\nhantar qadhausp\n[[SPLIT]]\nDalam Buku Qadha Solat ni, cik akan belajar tentang:\n✅ Cara kira berapa solat yang tertinggal\n[[SPLIT]]\nCik nak bayar online transfer atau COD?");

    mediaInbound('qadha');

    expect(outboundTrail())->toBe([
        'Kalau macam tu, ambil Pakej 1 pun dah okay, cik.',
        '[image]',
        "Dalam Buku Qadha Solat ni, cik akan belajar tentang:\n✅ Cara kira berapa solat yang tertinggal",
        'Cik nak bayar online transfer atau COD?',
    ]);
});

it('sends [[MEDIA:key]] markers in place, even mid-bubble', function () {
    CekbotMedia::factory()->video()->create(['key' => 'testimoni-1']);
    mediaAiSays("Ni testimoni pelanggan kami 👇\n[[MEDIA:testimoni-1]]\nBest kan?");

    mediaInbound('qadha');

    expect(outboundTrail())->toBe(['Ni testimoni pelanggan kami 👇', '[video]', 'Best kan?']);
});

it('keeps "hantar ..." lines as text when no such media exists', function () {
    CekbotMedia::factory()->create(['key' => 'qadhausp']);
    mediaAiSays("Boleh, saya hantar buku esok.\nhantar alamat ye");

    mediaInbound('qadha');

    expect(outboundTrail())->toBe(["Boleh, saya hantar buku esok.\nhantar alamat ye"]);
});

it('does not send the same media twice when placed inline and via the tool', function () {
    CekbotMedia::factory()->create(['key' => 'testimoni-1']);
    mediaToolTurn('testimoni-1', "Ni dia 👇\n[[MEDIA:testimoni-1]]");

    mediaInbound('qadha');

    expect(outboundTrail())->toBe(['Ni dia 👇', '[image]']);
});

it('tells the AI about [[SPLIT]] and inline media markers', function () {
    CekbotMedia::factory()->create(['key' => 'testimoni-1']);
    mediaAiSays('Hai');

    mediaInbound('qadha');

    OpenAI::assertSent(Chat::class, function (string $method, array $params) {
        $system = collect($params['messages'])->firstWhere('role', 'system')['content'] ?? '';

        return str_contains($system, '[[SPLIT]]') && str_contains($system, '[[MEDIA:key]]');
    });
});
