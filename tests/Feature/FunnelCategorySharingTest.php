<?php

use App\Models\Funnel;
use App\Models\FunnelCategory;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('categories created by one user are visible to every user', function () {
    $owner = User::factory()->create();
    $teammate = User::factory()->create();

    $category = FunnelCategory::factory()->for($owner)->create(['name' => 'Ustazah Balqis']);

    $response = $this->actingAs($teammate)->getJson('/api/v1/funnel-categories');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $category->id)
        ->assertJsonPath('data.0.name', 'Ustazah Balqis');
});

test('a funnel categorised by one user shows the same category for another user', function () {
    $owner = User::factory()->create();
    $teammate = User::factory()->create();

    $category = FunnelCategory::factory()->for($owner)->create(['name' => 'Purchase']);
    $funnel = Funnel::factory()->for($owner)->create(['funnel_category_id' => $category->id]);

    $response = $this->actingAs($teammate)->getJson('/api/v1/funnels');

    $response->assertOk();

    $row = collect($response->json('data'))->firstWhere('uuid', $funnel->uuid);

    expect($row)->not->toBeNull();
    expect($row['funnel_category_id'])->toBe($category->id);
    expect($row['category']['name'])->toBe('Purchase');
});

test('any user can rename a shared category', function () {
    $owner = User::factory()->create();
    $teammate = User::factory()->create();

    $category = FunnelCategory::factory()->for($owner)->create(['name' => 'Old Name']);

    $response = $this->actingAs($teammate)->putJson("/api/v1/funnel-categories/{$category->id}", [
        'name' => 'New Name',
    ]);

    $response->assertOk();

    expect($category->fresh()->name)->toBe('New Name');
});

test('any user can delete a shared category and its funnels become uncategorised', function () {
    $owner = User::factory()->create();
    $teammate = User::factory()->create();

    $category = FunnelCategory::factory()->for($owner)->create();
    $funnel = Funnel::factory()->for($owner)->create(['funnel_category_id' => $category->id]);

    $response = $this->actingAs($teammate)->deleteJson("/api/v1/funnel-categories/{$category->id}");

    $response->assertOk();

    expect(FunnelCategory::find($category->id))->toBeNull();
    expect($funnel->fresh()->funnel_category_id)->toBeNull();
});

test('any user can reorder shared categories', function () {
    $owner = User::factory()->create();
    $teammate = User::factory()->create();

    $first = FunnelCategory::factory()->for($owner)->create(['sort_order' => 0]);
    $second = FunnelCategory::factory()->for($owner)->create(['sort_order' => 1]);

    $response = $this->actingAs($teammate)->postJson('/api/v1/funnel-categories/reorder', [
        'categories' => [
            ['id' => $first->id, 'sort_order' => 1],
            ['id' => $second->id, 'sort_order' => 0],
        ],
    ]);

    $response->assertOk();

    expect($first->fresh()->sort_order)->toBe(1);
    expect($second->fresh()->sort_order)->toBe(0);
});

test('category names must be unique across all users', function () {
    $owner = User::factory()->create();
    $teammate = User::factory()->create();

    FunnelCategory::factory()->for($owner)->create(['name' => 'Promo']);

    $response = $this->actingAs($teammate)->postJson('/api/v1/funnel-categories', [
        'name' => 'Promo',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('name');
});
