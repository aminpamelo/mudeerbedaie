<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelAutomation;
use App\Models\FunnelStep;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function libraryFighter(array $attrs = []): User
{
    return User::factory()->create(array_merge(['role' => 'fighter'], $attrs));
}

/**
 * Build an HQ funnel with two linked steps, a product, a coupon and an
 * automation — enough surface to prove the deep copy works.
 */
function hqFunnel(array $attrs = []): Funnel
{
    $admin = User::factory()->create(['role' => 'admin']);

    $funnel = Funnel::factory()->create(array_merge([
        'user_id' => $admin->id,
        'name' => 'HQ Launch Funnel',
        'status' => 'published',
        'available_to_fighters' => true,
        'settings' => ['pixel_settings' => ['facebook' => ['pixel_id' => 'HQ-PIXEL']]],
    ], $attrs));

    $checkout = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'type' => 'checkout', 'sort_order' => 0]);
    $upsell = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'type' => 'upsell', 'sort_order' => 1]);

    $checkout->update(['next_step_id' => $upsell->id]);

    $checkout->products()->create([
        'product_id' => Product::factory()->create()->id,
        'type' => 'main',
        'name' => 'Main Offer',
        'funnel_price' => 100,
        'sort_order' => 0,
    ]);

    $funnel->coupons()->create([
        'code' => 'LAUNCH10',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    FunnelAutomation::factory()->create(['funnel_id' => $funnel->id])
        ->actions()->create([
            'action_type' => 'send_email',
            'action_config' => ['subject' => 'Hi', 'content' => 'Thanks', 'email_field' => 'contact.email'],
            'delay_minutes' => 0,
            'sort_order' => 0,
        ]);

    return $funnel->fresh();
}

/*
|--------------------------------------------------------------------------
| Model: copyForFighter
|--------------------------------------------------------------------------
*/

it('copies a funnel into a fighter-owned draft with a fresh slug and uuid', function () {
    $source = hqFunnel();
    $fighter = libraryFighter();

    $copy = $source->copyForFighter($fighter->id);

    expect($copy->user_id)->toBe($fighter->id)
        ->and($copy->status)->toBe('draft')
        ->and($copy->available_to_fighters)->toBeFalse()
        ->and($copy->uuid)->not->toBe($source->uuid)
        ->and($copy->slug)->not->toBe($source->slug)
        ->and($copy->id)->not->toBe($source->id);
});

it('deep-copies steps, products, coupons and automations and remaps step links', function () {
    $source = hqFunnel();
    $fighter = libraryFighter();

    $copy = $source->copyForFighter($fighter->id);

    expect($copy->steps()->count())->toBe(2)
        ->and($copy->coupons()->count())->toBe(1)
        ->and($copy->automations()->count())->toBe(1);

    // Product carried over.
    $copiedCheckout = $copy->steps()->where('type', 'checkout')->first();
    expect($copiedCheckout->products()->count())->toBe(1);

    // next_step_id now points at the COPY's upsell step, not the source's.
    $copiedUpsell = $copy->steps()->where('type', 'upsell')->first();
    expect($copiedCheckout->next_step_id)->toBe($copiedUpsell->id)
        ->and($copy->steps()->pluck('id'))->not->toContain($source->steps()->where('type', 'upsell')->first()->id);

    // Automation actions came along.
    expect($copy->automations()->first()->actions()->count())->toBe(1);
});

it('carries settings over verbatim (pixels included)', function () {
    $source = hqFunnel();
    $copy = $source->copyForFighter(libraryFighter()->id);

    expect($copy->settings['pixel_settings']['facebook']['pixel_id'])->toBe('HQ-PIXEL');
});

/*
|--------------------------------------------------------------------------
| Fighter portal: library page + copy endpoint
|--------------------------------------------------------------------------
*/

it('shows only available funnels in the fighter library', function () {
    $available = hqFunnel(['name' => 'Available One']);
    $hidden = Funnel::factory()->create([
        'user_id' => User::factory()->create(['role' => 'admin'])->id,
        'name' => 'Hidden One',
        'available_to_fighters' => false,
    ]);

    $this->actingAs(libraryFighter())
        ->get('/fighter/funnel-library')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FunnelLibrary', false)
            ->has('funnels', 1)
            ->where('funnels.0.uuid', $available->uuid)
        );
});

it('lets a fighter copy an available funnel as their own', function () {
    $source = hqFunnel();
    $fighter = libraryFighter();

    $this->actingAs($fighter)
        ->post("/fighter/funnel-library/{$source->uuid}/copy")
        ->assertRedirect();

    $copy = Funnel::query()->where('user_id', $fighter->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe('draft')
        ->and($copy->steps()->count())->toBe(2);
});

it('does not let a fighter copy a funnel that is not available', function () {
    $source = Funnel::factory()->create([
        'user_id' => User::factory()->create(['role' => 'admin'])->id,
        'available_to_fighters' => false,
    ]);

    $this->actingAs(libraryFighter())
        ->post("/fighter/funnel-library/{$source->uuid}/copy")
        ->assertNotFound();

    expect(Funnel::query()->where('user_id', '!=', $source->user_id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Admin toggle
|--------------------------------------------------------------------------
*/

it('lets an admin toggle a funnel available to fighters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $funnel = Funnel::factory()->create(['user_id' => $admin->id, 'available_to_fighters' => false]);

    $this->actingAs($admin);

    Volt::test('admin.funnel-list')
        ->call('toggleFighterAvailability', $funnel->id);

    expect($funnel->fresh()->available_to_fighters)->toBeTrue();

    Volt::test('admin.funnel-list')
        ->call('toggleFighterAvailability', $funnel->id);

    expect($funnel->fresh()->available_to_fighters)->toBeFalse();
});
