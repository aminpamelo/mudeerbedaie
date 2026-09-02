<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppGroupCollection;
use App\Models\WhatsAppGroupCollectionItem;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);

    $this->collection = WhatsAppGroupCollection::create([
        'name' => 'Group Safinah Latest',
        'is_active' => true,
    ]);
});

test('the add group modal no longer offers a class selector', function () {
    $html = Volt::test('admin.whatsapp-groups.manage', ['collection' => $this->collection])
        ->assertStatus(200)
        ->html();

    expect($html)->not->toContain('Cari kelas');
    expect($html)->not->toContain('Pilih kelas');
    expect($html)->not->toContain('class_id');
    // The invite link is now the primary field for adding a group.
    expect($html)->toContain('Link jemputan');
});

test('adding a group requires an invite link', function () {
    Volt::test('admin.whatsapp-groups.manage', ['collection' => $this->collection])
        ->call('addItem')
        ->set('label', 'Group Pagi')
        ->set('invite_link', '')
        ->call('saveItem')
        ->assertHasErrors(['invite_link' => 'required']);

    expect($this->collection->items()->count())->toBe(0);
});

test('adds a group from a manual invite link with no class', function () {
    Volt::test('admin.whatsapp-groups.manage', ['collection' => $this->collection])
        ->call('addItem')
        ->set('label', 'Group Pagi')
        ->set('invite_link', 'https://chat.whatsapp.com/ABC123')
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = WhatsAppGroupCollectionItem::query()
        ->where('collection_id', $this->collection->id)
        ->first();

    expect($item)->not->toBeNull()
        ->and($item->label)->toBe('Group Pagi')
        ->and($item->invite_link)->toBe('https://chat.whatsapp.com/ABC123')
        ->and($item->class_id)->toBeNull();
});
