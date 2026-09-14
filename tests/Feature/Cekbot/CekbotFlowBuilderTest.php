<?php

use App\Models\CekbotFlow;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotSession;
use App\Models\User;

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
        ->assertInertia(fn ($page) => $page->component('Flows/Show', false)->has('flow')->has('products'));
});

it('saves the flow and syncs its packages', function () {
    $flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1']);

    test()->actingAs($this->admin)
        ->put(route('cekbot.flows.update', $flow->id), [
            'name' => 'Funnel Siap',
            'is_active' => true,
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

it('deletes a flow', function () {
    $flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1']);

    test()->actingAs($this->admin)
        ->delete(route('cekbot.flows.destroy', $flow->id))
        ->assertRedirect(route('cekbot.flows'));

    expect(CekbotFlow::query()->find($flow->id))->toBeNull();
});
