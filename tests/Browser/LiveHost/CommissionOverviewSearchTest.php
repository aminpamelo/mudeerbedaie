<?php

use App\Models\User;

/**
 * Browser coverage for the client-side host search on the Commission Overview
 * page (`/livehost/commission`). All hosts are shipped to the page at once, so
 * the search box filters the already-rendered rows in the browser (no request).
 */
it('filters the host rows by name or email as you type', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    User::factory()->create(['role' => 'live_host', 'name' => 'Zulkifli Rahman', 'email' => 'zulkifli@example.com']);
    User::factory()->create(['role' => 'live_host', 'name' => 'Aisyah Humaira', 'email' => 'aisyah@example.com']);

    $this->actingAs($pic);

    $page = visit('/livehost/commission');

    // Both hosts render before searching.
    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Zulkifli Rahman')
        ->assertSee('Aisyah Humaira');

    // Typing a name narrows the list to the matching host only.
    $page
        ->fill('input[type="search"]', 'Zulkifli')
        ->assertSee('Zulkifli Rahman')
        ->assertDontSee('Aisyah Humaira')
        ->assertSee('1 of 2 hosts');

    // Searching by email fragment works too.
    $page
        ->fill('input[type="search"]', 'aisyah@')
        ->assertSee('Aisyah Humaira')
        ->assertDontSee('Zulkifli Rahman');

    // A non-matching query shows the empty state.
    $page
        ->fill('input[type="search"]', 'nobody-here-xyz')
        ->assertSee('No hosts match')
        ->assertNoJavascriptErrors();
});
