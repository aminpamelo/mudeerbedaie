<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotProduct;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->admin = User::factory()->admin()->create();
});

it('renders the products page', function () {
    CekbotProduct::factory()->create();

    $this->actingAs($this->admin)
        ->get('/admin/cekbot/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Products/Index', false)->has('products', 1));
});

it('creates a product with an uploaded image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post('/admin/cekbot/products', [
            'name' => 'Kurma Ajwa Premium',
            'price' => 99,
            'currency' => 'RM',
            'description' => 'Kurma premium dari Madinah.',
            'url' => 'https://shop.test/kurma',
            'images' => [UploadedFile::fake()->image('kurma.jpg')],
        ])
        ->assertRedirect();

    $product = CekbotProduct::first();
    expect($product->name)->toBe('Kurma Ajwa Premium')
        ->and($product->images)->toHaveCount(1);
    Storage::disk('public')->assertExists($product->images[0]);
});

it('validates the product name', function () {
    $this->actingAs($this->admin)
        ->post('/admin/cekbot/products', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('updates and deletes a product', function () {
    $product = CekbotProduct::factory()->create(['name' => 'Old']);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/products/{$product->id}", ['name' => 'New', 'price' => 50])
        ->assertRedirect();
    expect($product->fresh()->name)->toBe('New');

    $this->actingAs($this->admin)
        ->delete("/admin/cekbot/products/{$product->id}")
        ->assertRedirect();
    $this->assertModelMissing($product);
});

it('searches the catalogue', function () {
    $this->actingAs($this->admin)
        ->getJson('/admin/cekbot/products-search?q=zzz')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('feeds product knowledge into the AI reply', function () {
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'openai.api_key' => 'sk-test',
    ]);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'p-out'], 201)]);
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Kurma Ajwa kami RM99. Nak saya kongsi link?'], 'finish_reason' => 'stop']]]),
    ]);

    $session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $session->id, 'bot_enabled' => true, 'ai_enabled' => true]);
    CekbotProduct::factory()->create(['name' => 'Kurma Ajwa Premium', 'price' => 99]);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'pq1', 'from' => '60123@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'ada jual kurma?', 'type' => 'chat'],
    ])->assertOk();

    $out = \App\Models\CekbotMessage::query()->where('direction', 'out')->first();
    expect($out?->body)->toBe('Kurma Ajwa kami RM99. Nak saya kongsi link?');

    // The product list was injected into the AI prompt.
    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => $method === 'create'
        && str_contains(json_encode($params['messages'], JSON_UNESCAPED_UNICODE), 'Kurma Ajwa Premium'));
});
