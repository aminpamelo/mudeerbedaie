<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VaultCategory;
use App\Models\VaultCredential;
use App\Models\VaultTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('credential encrypts and decrypts password', function () {
    $credential = VaultCredential::factory()->create([
        'password' => 'super-secret-123',
    ]);

    // Fresh fetch from DB to confirm round-trip
    $fresh = VaultCredential::find($credential->id);

    expect($fresh->password)->toBe('super-secret-123');

    // The raw DB value should NOT be the plain text
    $raw = DB::table('vault_credentials')->where('id', $credential->id)->value('password');
    expect($raw)->not->toBe('super-secret-123');
});

test('credential encrypts notes', function () {
    $credential = VaultCredential::factory()->create([
        'notes' => 'These are private notes.',
    ]);

    $fresh = VaultCredential::find($credential->id);

    expect($fresh->notes)->toBe('These are private notes.');

    $raw = DB::table('vault_credentials')->where('id', $credential->id)->value('notes');
    expect($raw)->not->toBe('These are private notes.');
});

test('credential belongs to category', function () {
    $category = VaultCategory::create(['name' => 'Social Media', 'slug' => 'social-media']);
    $credential = VaultCredential::factory()->create(['category_id' => $category->id]);

    expect($credential->category)->toBeInstanceOf(VaultCategory::class);
    expect($credential->category->id)->toBe($category->id);
});

test('credential has many tags', function () {
    $credential = VaultCredential::factory()->create();
    $tag1 = VaultTag::create(['name' => 'Production', 'slug' => 'production']);
    $tag2 = VaultTag::create(['name' => 'Staging', 'slug' => 'staging']);

    $credential->tags()->attach([$tag1->id, $tag2->id]);

    expect($credential->fresh()->tags)->toHaveCount(2);
    expect($credential->tags->pluck('name')->toArray())->toContain('Production', 'Staging');
});

test('credential search scope filters by name, username, and url', function () {
    $user = User::factory()->create();

    VaultCredential::factory()->create(['name' => 'TikTok Shop', 'username' => 'admin', 'url' => 'https://tiktok.com', 'created_by' => $user->id]);
    VaultCredential::factory()->create(['name' => 'Hosting Panel', 'username' => 'root', 'url' => 'https://cpanel.example.com', 'created_by' => $user->id]);
    VaultCredential::factory()->create(['name' => 'Email SMTP', 'username' => 'mailer', 'url' => 'https://smtp.example.com', 'created_by' => $user->id]);

    // Search by name
    expect(VaultCredential::search('TikTok')->count())->toBe(1);

    // Search by username
    expect(VaultCredential::search('root')->count())->toBe(1);

    // Search by URL
    expect(VaultCredential::search('cpanel')->count())->toBe(1);

    // Blank search returns all
    expect(VaultCredential::search('')->count())->toBe(3);
    expect(VaultCredential::search(null)->count())->toBe(3);
});
