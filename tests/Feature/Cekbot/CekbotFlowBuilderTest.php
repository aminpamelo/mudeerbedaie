<?php

use App\Models\CekbotFlow;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotSession;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['label' => 'Kedai ABC']);
});

it('renders the flows page for admins', function () {
    test()->actingAs($this->admin)
        ->get('/admin/cekbot/flows')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Flows/Index', false)->has('sessions'));
});

it('forbids non-admins from the flows page', function () {
    $user = User::factory()->create();

    test()->actingAs($user)->get('/admin/cekbot/flows')->assertForbidden();
});

it('creates a flow and redirects to the builder', function () {
    test()->actingAs($this->admin)
        ->post(route('cekbot.flows.store'), [
            'cekbot_session_id' => $this->session->id,
            'name' => 'Funnel Kurma',
        ])
        ->assertRedirect();

    $flow = CekbotFlow::query()->firstWhere('name', 'Funnel Kurma');
    expect($flow)->not->toBeNull()
        ->and($flow->cekbot_session_id)->toBe($this->session->id)
        ->and($flow->is_active)->toBeFalse();
});

it('renders the builder page for a flow', function () {
    $flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1']);

    test()->actingAs($this->admin)
        ->get(route('cekbot.flows.show', $flow->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Flows/Show', false)->has('flow')->has('catalogProducts')->has('cekbotProducts'));
});

it('links a package to a catalogue product and resolves its price', function () {
    $product = Product::factory()->create(['name' => 'Kelas Combo', 'base_price' => 160]);
    $flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1']);

    test()->actingAs($this->admin)
        ->put(route('cekbot.flows.update', $flow->id), [
            'name' => 'F1',
            'match_type' => 'contains',
            'trigger_keywords' => ['minat'],
            'ask_payment' => true,
            'payment_transfer_enabled' => true,
            'payment_cod_enabled' => true,
            'ask_name' => true,
            'packages' => [
                // No price override — should fall back to the catalogue price.
                ['product_id' => $product->id, 'cekbot_product_id' => null, 'label' => 'Combo', 'price' => null, 'currency' => 'RM'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $pkg = $flow->packages()->first();
    expect($pkg->product_id)->toBe($product->id)
        ->and($pkg->effectivePrice())->toBe(160.0)
        ->and($pkg->orderProductId())->toBe($product->id);
});

it('saves the flow and syncs its packages', function () {
    $flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1']);

    test()->actingAs($this->admin)
        ->put(route('cekbot.flows.update', $flow->id), [
            'name' => 'Funnel Siap',
            'is_active' => true,
            'use_ai' => true,
            'ai_instructions' => 'Guna bahasa santai.',
            'match_type' => 'contains',
            'trigger_keywords' => ['minat', 'nak order', ''],
            'welcome_message' => 'Salam!',
            'ask_payment' => true,
            'payment_transfer_enabled' => true,
            'payment_cod_enabled' => true,
            'bank_details' => 'Maybank 123',
            'ask_name' => true,
            'packages' => [
                ['cekbot_product_id' => null, 'label' => 'Pakej A', 'price' => 97, 'currency' => 'RM'],
                ['cekbot_product_id' => null, 'label' => 'Pakej B', 'price' => 197, 'currency' => 'RM'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $flow->refresh();
    expect($flow->name)->toBe('Funnel Siap')
        ->and($flow->is_active)->toBeTrue()
        ->and($flow->use_ai)->toBeTrue()
        ->and($flow->ai_instructions)->toBe('Guna bahasa santai.')
        ->and($flow->trigger_keywords)->toBe(['minat', 'nak order']) // blank dropped
        ->and($flow->packages()->count())->toBe(2);

    // Re-save with one package removed → sync deletes the extra row.
    $keep = $flow->packages()->orderBy('id')->first();
    test()->actingAs($this->admin)
        ->put(route('cekbot.flows.update', $flow->id), [
            'name' => 'Funnel Siap',
            'match_type' => 'contains',
            'trigger_keywords' => ['minat'],
            'ask_payment' => true,
            'payment_transfer_enabled' => true,
            'payment_cod_enabled' => true,
            'ask_name' => true,
            'packages' => [
                ['id' => $keep->id, 'cekbot_product_id' => null, 'label' => 'Pakej A (kekal)', 'price' => 99, 'currency' => 'RM'],
            ],
        ])
        ->assertRedirect();

    expect($flow->packages()->count())->toBe(1)
        ->and(CekbotFlowPackage::query()->find($keep->id)->label)->toBe('Pakej A (kekal)');
});

it('toggles a flow active state', function () {
    $flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1', 'is_active' => false]);

    test()->actingAs($this->admin)
        ->put(route('cekbot.flows.toggle', $flow->id), ['is_active' => true])
        ->assertRedirect();

    expect($flow->refresh()->is_active)->toBeTrue();
});

it('uploads and removes a transfer QR image', function () {
    Storage::fake('public');
    $flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1']);

    test()->actingAs($this->admin)
        ->post(route('cekbot.flows.bank-image.store', $flow->id), [
            'bank_image' => UploadedFile::fake()->image('qr.png'),
        ])
        ->assertRedirect();

    $flow->refresh();
    expect($flow->bank_image)->not->toBeNull();
    Storage::disk('public')->assertExists($flow->bank_image);

    test()->actingAs($this->admin)
        ->delete(route('cekbot.flows.bank-image.destroy', $flow->id))
        ->assertRedirect();

    expect($flow->refresh()->bank_image)->toBeNull();
});

it('deletes a flow', function () {
    $flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1']);

    test()->actingAs($this->admin)
        ->delete(route('cekbot.flows.destroy', $flow->id))
        ->assertRedirect(route('cekbot.flows'));

    expect(CekbotFlow::query()->find($flow->id))->toBeNull();
});
