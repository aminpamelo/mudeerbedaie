<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppGroupCollection;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

test('manage page renders the QR with a download button wired to the download handler', function () {
    $collection = WhatsAppGroupCollection::create([
        'name' => 'Group Safinah Latest',
        'is_active' => true,
    ]);

    $html = Volt::test('admin.whatsapp-groups.manage', ['collection' => $collection])
        ->assertStatus(200)
        ->html();

    // The QR itself still renders as inline SVG (source for the client-side export).
    expect($html)->toContain('<svg');
    // Download button is present and wired to the Alpine handler.
    expect($html)->toContain('Muat turun QR');
    expect($html)->toContain('downloadQr()');
    // File is named after the collection slug.
    expect($html)->toContain('qr-'.$collection->slug.'.png');
});
