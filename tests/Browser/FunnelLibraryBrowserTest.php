<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\User;

/**
 * Browser tests for the Fighter Funnel Library: admins flag a funnel available,
 * fighters browse those funnels and copy one into their own workspace.
 */
function browserLibraryFunnel(bool $available = true): Funnel
{
    $admin = User::factory()->admin()->create();

    $funnel = Funnel::factory()->create([
        'user_id' => $admin->id,
        'name' => 'HQ Launch Funnel',
        'description' => 'Ready-made launch funnel for fighters.',
        'status' => 'published',
        'available_to_fighters' => $available,
    ]);

    FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'type' => 'checkout', 'sort_order' => 0]);

    return $funnel;
}

it('shows the Fighters badge on the admin funnel list for available funnels', function () {
    $admin = User::factory()->admin()->create();
    browserLibraryFunnel(available: true);

    $this->actingAs($admin);

    visit('/admin/funnels')
        ->assertNoJavascriptErrors()
        ->assertSee('HQ Launch Funnel')
        ->assertSee('Fighters'); // the orange availability badge in the Status column
});

it('lets a fighter browse the library and copy a funnel as their own', function () {
    $funnel = browserLibraryFunnel(available: true);
    $fighter = User::factory()->create(['role' => 'fighter']);

    $this->actingAs($fighter);

    visit('/fighter/funnel-library')
        ->assertNoJavascriptErrors()
        ->assertSee('Funnel Library')
        ->assertSee('HQ Launch Funnel')
        ->assertSee('Copy to my funnels')
        ->click('Copy to my funnels');

    // The copy button POSTs then redirects into the builder. Poll for the new
    // fighter-owned funnel rather than racing the async request/navigation.
    retry(20, function () use ($fighter, $funnel) {
        $copy = Funnel::query()->where('user_id', $fighter->id)->first();
        expect($copy)->not->toBeNull()
            ->and($copy->id)->not->toBe($funnel->id)
            ->and($copy->status)->toBe('draft')
            ->and($copy->steps()->count())->toBe(1);
    }, 250);
});

it('shows an empty state when no funnels are available to fighters', function () {
    // A funnel exists but is NOT flagged available.
    browserLibraryFunnel(available: false);
    $fighter = User::factory()->create(['role' => 'fighter']);

    $this->actingAs($fighter);

    visit('/fighter/funnel-library')
        ->assertNoJavascriptErrors()
        ->assertSee('No funnels available yet')
        ->assertDontSee('HQ Launch Funnel');
});
