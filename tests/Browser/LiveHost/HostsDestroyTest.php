<?php

use App\Models\User;

it('deletes a host from the show page', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Delete Target']);
    $this->actingAs($pic);

    $page = visit("/livehost/hosts/{$host->id}");

    $page->assertSee('Delete Target')
        ->click('Delete')
        ->assertSee('Delete Delete Target?')
        ->click('Confirm delete')
        ->assertPathIs('/livehost/hosts')
        ->assertDontSee('Delete Target')
        ->assertNoJavascriptErrors();
});
