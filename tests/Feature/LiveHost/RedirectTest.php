<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('redirects /admin/live-hosts to /livehost/hosts for admin_livehost', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    actingAs($pic)->get('/admin/live-hosts')
        ->assertRedirect('/livehost/hosts');
});

it('redirects /admin/live-hosts/create to /livehost/hosts/create', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    actingAs($pic)->get('/admin/live-hosts/create')
        ->assertRedirect('/livehost/hosts/create');
});

it('redirects /admin/live-hosts/{id} to /livehost/hosts/{id}', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($pic)->get("/admin/live-hosts/{$host->id}")
        ->assertRedirect("/livehost/hosts/{$host->id}");
});

it('redirects /admin/live-hosts/{id}/edit to /livehost/hosts/{id}/edit', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($pic)->get("/admin/live-hosts/{$host->id}/edit")
        ->assertRedirect("/livehost/hosts/{$host->id}/edit");
});

it('redirects for admin role too', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->get('/admin/live-hosts')
        ->assertRedirect('/livehost/hosts');
});

it('uses 301 status for the permanent redirects', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->get('/admin/live-hosts')
        ->assertStatus(301);

    actingAs($admin)->get('/admin/live-hosts/create')
        ->assertStatus(301);
});
