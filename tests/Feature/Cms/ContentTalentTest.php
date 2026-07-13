<?php

declare(strict_types=1);

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('lists only live hosts for the talent picker', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Host One']);
    User::factory()->create(['role' => 'employee', 'name' => 'Not A Host']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/cms/live-hosts')
        ->assertSuccessful();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Host One')
        ->and($names)->not->toContain('Not A Host');
});

it('filters live hosts by search term', function () {
    User::factory()->create(['role' => 'live_host', 'name' => 'Aisyah Rahman']);
    User::factory()->create(['role' => 'live_host', 'name' => 'Zulkifli Bakar']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/cms/live-hosts?search=Aisyah')
        ->assertSuccessful();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Aisyah Rahman')
        ->and($names)->not->toContain('Zulkifli Bakar');
});

it('attaches a live host as talent', function () {
    $content = Content::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($this->admin)
        ->postJson("/api/cms/contents/{$content->id}/talents", ['user_id' => $host->id])
        ->assertCreated()
        ->assertJsonPath('data.0.id', $host->id);

    expect($content->fresh()->talents()->count())->toBe(1);
});

it('rejects a non-live-host user as talent', function () {
    $content = Content::factory()->create();
    $employee = User::factory()->create(['role' => 'employee']);

    $this->actingAs($this->admin)
        ->postJson("/api/cms/contents/{$content->id}/talents", ['user_id' => $employee->id])
        ->assertStatus(422);

    expect($content->fresh()->talents()->count())->toBe(0);
});

it('does not duplicate an already-attached talent', function () {
    $content = Content::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($this->admin)
            ->postJson("/api/cms/contents/{$content->id}/talents", ['user_id' => $host->id])
            ->assertCreated();
    }

    expect($content->fresh()->talents()->count())->toBe(1);
});

it('supports multiple talents on one content', function () {
    $content = Content::factory()->create();
    $hosts = User::factory()->count(3)->create(['role' => 'live_host']);

    foreach ($hosts as $host) {
        $this->actingAs($this->admin)
            ->postJson("/api/cms/contents/{$content->id}/talents", ['user_id' => $host->id])
            ->assertCreated();
    }

    expect($content->fresh()->talents()->count())->toBe(3);
});

it('detaches a talent', function () {
    $content = Content::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);
    $content->talents()->attach($host->id);

    $this->actingAs($this->admin)
        ->deleteJson("/api/cms/contents/{$content->id}/talents/{$host->id}")
        ->assertSuccessful();

    expect($content->fresh()->talents()->count())->toBe(0);
});

it('includes talents with avatar_url when showing content', function () {
    $content = Content::factory()->create();
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Featured Host']);
    $content->talents()->attach($host->id);

    $this->actingAs($this->admin)
        ->getJson("/api/cms/contents/{$content->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.talents.0.name', 'Featured Host')
        ->assertJsonPath('data.talents.0.avatar_url', null);
});
