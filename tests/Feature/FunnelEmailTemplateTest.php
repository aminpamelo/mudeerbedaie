<?php

declare(strict_types=1);

use App\Models\FunnelEmailTemplate;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

test('can list funnel email templates', function () {
    FunnelEmailTemplate::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/funnel-email-templates');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('can filter templates by active status', function () {
    FunnelEmailTemplate::factory()->count(2)->create();
    FunnelEmailTemplate::factory()->inactive()->create();

    $response = $this->getJson('/api/v1/funnel-email-templates?active=true');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('can filter templates by category', function () {
    FunnelEmailTemplate::factory()->category('purchase')->count(2)->create();
    FunnelEmailTemplate::factory()->category('cart')->create();

    $response = $this->getJson('/api/v1/funnel-email-templates?category=purchase');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('can search templates by name', function () {
    FunnelEmailTemplate::factory()->create(['name' => 'Purchase Confirmation']);
    FunnelEmailTemplate::factory()->create(['name' => 'Cart Abandonment']);

    $response = $this->getJson('/api/v1/funnel-email-templates?search=Purchase');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('can show a single template', function () {
    $template = FunnelEmailTemplate::factory()->create();

    $response = $this->getJson("/api/v1/funnel-email-templates/{$template->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $template->id)
        ->assertJsonPath('data.name', $template->name);
});

test('can create a template', function () {
    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'name' => 'Purchase Confirmation',
        'subject' => 'Order #{{order.number}} confirmed',
        'content' => 'Hi {{contact.first_name}}, thank you!',
        'category' => 'purchase',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Purchase Confirmation');

    expect(FunnelEmailTemplate::where('name', 'Purchase Confirmation')->exists())->toBeTrue();
});

test('can create a template with custom slug', function () {
    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'name' => 'My Template',
        'slug' => 'my-custom-slug',
        'content' => 'Hello',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'my-custom-slug');
});

test('auto-generates slug when not provided', function () {
    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'name' => 'Purchase Confirmation',
        'content' => 'Hello',
    ]);

    $response->assertCreated();
    expect($response->json('data.slug'))->toStartWith('purchase-confirmation');
});

test('can update a template', function () {
    $template = FunnelEmailTemplate::factory()->create();

    $response = $this->putJson("/api/v1/funnel-email-templates/{$template->id}", [
        'name' => 'Updated Name',
        'subject' => 'New Subject',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.subject', 'New Subject');
});

test('can delete a template', function () {
    $template = FunnelEmailTemplate::factory()->create();

    $response = $this->deleteJson("/api/v1/funnel-email-templates/{$template->id}");

    $response->assertSuccessful();
    expect(FunnelEmailTemplate::find($template->id))->toBeNull();
    expect(FunnelEmailTemplate::withTrashed()->find($template->id))->not->toBeNull();
});

test('can duplicate a template', function () {
    $template = FunnelEmailTemplate::factory()->create([
        'name' => 'Original Template',
        'subject' => 'Original Subject',
        'content' => 'Original Content',
    ]);

    $response = $this->postJson("/api/v1/funnel-email-templates/{$template->id}/duplicate");

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Original Template (Copy)')
        ->assertJsonPath('data.subject', 'Original Subject')
        ->assertJsonPath('data.content', 'Original Content');

    expect(FunnelEmailTemplate::count())->toBe(2);
});

test('validates required name on create', function () {
    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'subject' => 'Some subject',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('validates unique slug on create', function () {
    FunnelEmailTemplate::factory()->create(['slug' => 'existing-slug']);

    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'name' => 'New Template',
        'slug' => 'existing-slug',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});
