<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Browser smoke test for the admin dashboard "Front Store" button, which opens
 * the public storefront so admins can view their live shop — and, crucially,
 * keep browsing it without being redirected back to the admin dashboard.
 */
it('shows a Front Store button on the admin dashboard linking to the storefront', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    visit('/dashboard')
        ->assertNoJavaScriptErrors()
        ->assertSee('Front Store')
        ->assertPresent('a[href$="/storefront"]');
});

it('lets a logged-in admin browse the storefront home without bouncing to the dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    // The store nav (logo, "Utama", "Pakej") points at /storefront, which renders
    // the shop for authenticated users instead of redirecting to the dashboard.
    visit('/storefront')
        ->assertNoJavaScriptErrors()
        ->assertPathIs('/storefront')
        ->assertPresent('a[href$="/storefront"]')
        ->assertPresent('a[href$="/shop"]');
});

it('opens the public storefront for guests', function () {
    visit('/storefront')->assertNoJavaScriptErrors()->assertPathIs('/storefront');
});
