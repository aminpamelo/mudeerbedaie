<?php

use App\Models\CekbotConversation;
use App\Models\CekbotLabel;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('seeds the four default labels via migration', function () {
    expect(CekbotLabel::pluck('key')->sort()->values()->all())
        ->toBe(['baru', 'pending', 'penting', 'selesai']);
});

it('creates a label with a slugged key', function () {
    $this->actingAs($this->admin)
        ->from('/admin/cekbot/leads')
        ->post('/admin/cekbot/leads/labels', ['name' => 'Nak Beli', 'color' => 'violet'])
        ->assertRedirect('/admin/cekbot/leads');

    $label = CekbotLabel::where('name', 'Nak Beli')->first();

    expect($label)->not->toBeNull()
        ->and($label->key)->toBe('nak-beli')
        ->and($label->color)->toBe('violet');
});

it('appends a numeric suffix when the slug collides', function () {
    CekbotLabel::factory()->create(['key' => 'vip', 'name' => 'VIP']);

    $this->actingAs($this->admin)
        ->post('/admin/cekbot/leads/labels', ['name' => 'VIP', 'color' => 'amber']);

    expect(CekbotLabel::where('key', 'vip-2')->exists())->toBeTrue();
});

it('rejects an invalid colour', function () {
    $this->actingAs($this->admin)
        ->from('/admin/cekbot/leads')
        ->post('/admin/cekbot/leads/labels', ['name' => 'X', 'color' => 'chartreuse'])
        ->assertSessionHasErrors('color');

    expect(CekbotLabel::where('name', 'X')->exists())->toBeFalse();
});

it('updates a label without changing its key', function () {
    $label = CekbotLabel::factory()->create(['key' => 'panas', 'name' => 'Panas', 'color' => 'red']);

    $this->actingAs($this->admin)
        ->put("/admin/cekbot/leads/labels/{$label->id}", ['name' => 'Sangat Panas', 'color' => 'orange'])
        ->assertRedirect();

    $label->refresh();

    expect($label->key)->toBe('panas')
        ->and($label->name)->toBe('Sangat Panas')
        ->and($label->color)->toBe('orange');
});

it('deletes a label and detaches it from conversations', function () {
    $session = CekbotSession::factory()->create();
    $label = CekbotLabel::factory()->create(['key' => 'buang', 'name' => 'Buang']);
    $conversation = CekbotConversation::factory()->create([
        'cekbot_session_id' => $session->id,
        'labels' => ['buang', 'penting'],
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/cekbot/leads/labels/{$label->id}")
        ->assertRedirect();

    expect(CekbotLabel::find($label->id))->toBeNull()
        ->and($conversation->fresh()->labels)->toBe(['penting']);
});

it('tags a conversation with a DB label and rejects unknown keys', function () {
    $session = CekbotSession::factory()->create();
    CekbotLabel::factory()->create(['key' => 'custom', 'name' => 'Custom']);
    $conversation = CekbotConversation::factory()->create(['cekbot_session_id' => $session->id]);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/inbox/{$conversation->id}/labels", ['labels' => ['custom']])
        ->assertSessionHasNoErrors();

    expect($conversation->fresh()->labels)->toBe(['custom']);

    $this->actingAs($this->admin)
        ->from('/admin/cekbot/inbox')
        ->post("/admin/cekbot/inbox/{$conversation->id}/labels", ['labels' => ['does-not-exist']])
        ->assertSessionHasErrors('labels.0');
});
