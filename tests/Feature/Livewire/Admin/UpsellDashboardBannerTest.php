<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::flush();
});

it('shows the commission change banner by default', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->assertSee('Commission calculation updated');
});

it('hides the banner after dismissal', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->call('dismissBanner')
        ->assertDontSee('Commission calculation updated');
});

it('keeps the banner dismissed across reloads', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    // First mount: dismiss
    Volt::test('admin.upsell-dashboard')
        ->call('dismissBanner');

    // Second mount: should not see banner
    Volt::test('admin.upsell-dashboard')
        ->assertDontSee('Commission calculation updated');
});

it('dismissal is per-user', function () {
    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();

    $this->actingAs($admin1);
    Volt::test('admin.upsell-dashboard')->call('dismissBanner');

    // admin2 still sees it
    $this->actingAs($admin2);
    Volt::test('admin.upsell-dashboard')
        ->assertSee('Commission calculation updated');
});
