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

it('renders the product detail page', function () {
    $product = CekbotProduct::factory()->create(['name' => 'Kurma Ajwa']);

    $this->actingAs($this->admin)
        ->get("/admin/cekbot/products/{$product->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Products/Show', false)
            ->where('product.name', 'Kurma Ajwa'));
});

it('saves product knowledge and FAQ pairs, dropping blank rows', function () {
    $product = CekbotProduct::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/products/{$product->id}/knowledge", [
            'knowledge' => 'Bahan: 100% Ajwa gred A. Free postage.',
            'faqs' => [
                ['question' => 'Berapa lama penghantaran?', 'answer' => '1–3 hari.'],
                ['question' => '', 'answer' => ''],
            ],
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->knowledge)->toBe('Bahan: 100% Ajwa gred A. Free postage.')
        ->and($product->faqPairs())->toHaveCount(1)
        ->and($product->faqPairs()[0]['question'])->toBe('Berapa lama penghantaran?');
});

it('exposes product knowledge to the search_products AI tool', function () {
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'openai.api_key' => 'sk-test',
    ]);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'k-out'], 201)]);
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [
            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'search_products', 'arguments' => json_encode(['query' => 'kurma'])]],
        ]], 'finish_reason' => 'tool_calls']]]),
        CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Penghantaran 1-3 hari.'], 'finish_reason' => 'stop']]]),
    ]);

    $session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $session->id, 'bot_enabled' => true, 'ai_enabled' => true]);
    CekbotProduct::factory()->create([
        'name' => 'Kurma Ajwa Premium',
        'knowledge' => 'Free postage semenanjung dalam 1-3 hari.',
        'faqs' => [['question' => 'Boleh COD?', 'answer' => 'Ya, di Kuala Lumpur.']],
    ]);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'kq1', 'from' => '60123@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'berapa lama pos kurma?', 'type' => 'chat'],
    ])->assertOk();

    // The tool result (2nd call carries the tool message) contained the knowledge + FAQ.
    OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $method, array $params) {
        $json = json_encode($params['messages'], JSON_UNESCAPED_UNICODE);

        return str_contains($json, 'Free postage semenanjung') && str_contains($json, 'Boleh COD?');
    });
});

it('adds gallery images from the detail page', function () {
    Storage::fake('public');
    $product = CekbotProduct::factory()->create(['images' => []]);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->images)->toHaveCount(2);
    Storage::disk('public')->assertExists($product->images[0]);
});

it('removes a gallery image and deletes the file', function () {
    Storage::fake('public');
    $keep = UploadedFile::fake()->image('keep.jpg')->store('cekbot-products', 'public');
    $drop = UploadedFile::fake()->image('drop.jpg')->store('cekbot-products', 'public');
    $product = CekbotProduct::factory()->create(['images' => [$keep, $drop]]);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/products/{$product->id}/images", ['removed_images' => [$drop]])
        ->assertRedirect();

    $product->refresh();
    expect($product->images)->toBe([$keep]);
    Storage::disk('public')->assertMissing($drop);
    Storage::disk('public')->assertExists($keep);
});

it('adds a testimonial with an image and lists it on the detail page', function () {
    Storage::fake('public');
    $product = CekbotProduct::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/products/{$product->id}/testimonials", [
            'author' => 'Puan Aisyah',
            'text' => 'Kurma sangat sedap & fresh!',
            'image' => UploadedFile::fake()->image('review.jpg'),
        ])
        ->assertRedirect();

    $testimonial = $product->testimonials()->first();
    expect($testimonial)->not->toBeNull()
        ->and($testimonial->author)->toBe('Puan Aisyah');
    Storage::disk('public')->assertExists($testimonial->image);

    $this->actingAs($this->admin)
        ->get("/admin/cekbot/products/{$product->id}")
        ->assertInertia(fn (Assert $page) => $page->component('Products/Show', false)->has('product.testimonials', 1));
});

it('rejects a testimonial with neither text nor image', function () {
    $product = CekbotProduct::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/products/{$product->id}/testimonials", ['author' => 'Ali'])
        ->assertSessionHasErrors('text');

    expect($product->testimonials()->count())->toBe(0);
});

it('deletes a testimonial and its image', function () {
    Storage::fake('public');
    $product = CekbotProduct::factory()->create();
    $path = UploadedFile::fake()->image('r.jpg')->store('cekbot-testimonials', 'public');
    $testimonial = $product->testimonials()->create(['text' => 'Bagus!', 'image' => $path]);

    $this->actingAs($this->admin)
        ->delete("/admin/cekbot/products/{$product->id}/testimonials/{$testimonial->id}")
        ->assertRedirect();

    $this->assertModelMissing($testimonial);
    Storage::disk('public')->assertMissing($path);
});

it('exposes testimonials to the search_products AI tool', function () {
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'openai.api_key' => 'sk-test',
    ]);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 't-out'], 201)]);
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [
            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'search_products', 'arguments' => json_encode(['query' => 'kurma'])]],
        ]], 'finish_reason' => 'tool_calls']]]),
        CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Ramai pelanggan puas hati!'], 'finish_reason' => 'stop']]]),
    ]);

    $session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $session->id, 'bot_enabled' => true, 'ai_enabled' => true]);
    $product = CekbotProduct::factory()->create(['name' => 'Kurma Ajwa Premium']);
    $product->testimonials()->create(['author' => 'Puan Aisyah', 'text' => 'Sudah repeat 3 kali, memang berbaloi!']);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'tq1', 'from' => '60123@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'ada review tak kurma ni?', 'type' => 'chat'],
    ])->assertOk();

    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => str_contains(
        json_encode($params['messages'], JSON_UNESCAPED_UNICODE), 'Sudah repeat 3 kali'
    ));
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
