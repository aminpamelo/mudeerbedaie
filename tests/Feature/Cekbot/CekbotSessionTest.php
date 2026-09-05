<?php

use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'services.waha.session' => 'default',
    ]);
    $this->admin = User::factory()->admin()->create();
});

it('renders the sessions page for admins', function () {
    Http::fake(['waha.test/api/sessions*' => Http::response([], 200)]);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Index', false)
            ->has('sessions')
            ->where('waha.configured', true)
            ->where('waha.reachable', true)
        );
});

it('flags a server that blocks the write API (403) as not manageable', function () {
    Http::fake([
        'waha.test/*/auth/qr' => Http::response(['message' => 'Forbidden', 'statusCode' => 403], 403),
        'waha.test/api/sessions*' => Http::response([], 200),
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('waha.canManage', false)
            ->where('waha.dashboardUrl', 'https://waha.test/dashboard')
        );
});

it('allows in-app management when the write API is open', function () {
    Http::fake([
        'waha.test/*/auth/qr' => Http::response(['message' => 'not found', 'statusCode' => 404], 404),
        'waha.test/api/sessions*' => Http::response([], 200),
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('waha.canManage', true));
});

it('surfaces a friendly message when WAHA forbids session create', function () {
    $session = CekbotSession::factory()->create(['status' => null]);
    Http::fake([
        'waha.test/*' => Http::response(['message' => 'Forbidden', 'statusCode' => 403], 403),
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/admin/cekbot/sessions/{$session->id}/connect")
        ->assertStatus(502)
        ->assertJsonPath('ok', false);

    expect($response->json('error'))->toContain('403 Forbidden');
});

it('forbids non-admins', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/cekbot')
        ->assertForbidden();
});

it('imports WAHA sessions that are not tracked locally', function () {
    Http::fake(['waha.test/api/sessions*' => Http::response([
        ['name' => 'default', 'status' => 'WORKING', 'me' => ['id' => '60111@c.us'], 'engine' => ['engine' => 'GOWS']],
    ], 200)]);

    $this->actingAs($this->admin)->get('/admin/cekbot')->assertOk();

    $this->assertDatabaseHas('cekbot_sessions', [
        'session_name' => 'default',
        'phone_number' => '60111',
        'status' => 'WORKING',
    ]);
});

it('marks the server unreachable without crashing when WAHA is down', function () {
    Http::fake(['waha.test/*' => Http::response('', 500)]);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('waha.reachable', false));
});

it('adds a number and flags it for connect', function () {
    $this->actingAs($this->admin)
        ->post('/admin/cekbot/sessions', ['label' => 'CS Utama'])
        ->assertRedirect()
        ->assertSessionHas('connectSessionId');

    $this->assertDatabaseHas('cekbot_sessions', [
        'label' => 'CS Utama',
        'created_by' => $this->admin->id,
    ]);
});

it('validates the label when adding a number', function () {
    $this->actingAs($this->admin)
        ->post('/admin/cekbot/sessions', ['label' => ''])
        ->assertSessionHasErrors('label');
});

it('edits a number label and notes', function () {
    $session = CekbotSession::factory()->create(['label' => 'Old']);

    $this->actingAs($this->admin)
        ->put("/admin/cekbot/sessions/{$session->id}", ['label' => 'New', 'notes' => 'hi'])
        ->assertRedirect();

    expect($session->fresh()->label)->toBe('New')
        ->and($session->fresh()->notes)->toBe('hi');
});

it('deletes a number and asks WAHA to remove the session', function () {
    Http::fake(['waha.test/api/sessions/*' => Http::response([], 200)]);
    $session = CekbotSession::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/cekbot/sessions/{$session->id}")
        ->assertRedirect();

    $this->assertModelMissing($session);
    Http::assertSent(fn ($req) => $req->method() === 'DELETE'
        && str_contains($req->url(), "/api/sessions/{$session->session_name}"));
});

it('connect returns a QR data uri when the session is scannable', function () {
    $session = CekbotSession::factory()->create(['status' => null]);

    Http::fake([
        'waha.test/*/auth/qr' => Http::response(['mimetype' => 'image/png', 'data' => 'QUJD'], 200),
        'waha.test/api/sessions/*' => Http::response(['name' => $session->session_name, 'status' => 'SCAN_QR_CODE'], 200),
        'waha.test/api/sessions' => Http::response(['name' => $session->session_name, 'status' => 'STARTING'], 201),
    ]);

    $this->actingAs($this->admin)
        ->postJson("/admin/cekbot/sessions/{$session->id}/connect")
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status', 'SCAN_QR_CODE')
        ->assertJsonPath('qr', 'data:image/png;base64,QUJD');
});

it('logs out a working number', function () {
    Http::fake(['waha.test/api/sessions/*/logout' => Http::response([], 200)]);
    $session = CekbotSession::factory()->working()->create();

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/sessions/{$session->id}/logout")
        ->assertRedirect();

    expect($session->fresh()->phone_number)->toBeNull()
        ->and($session->fresh()->status)->toBe('SCAN_QR_CODE');
});
