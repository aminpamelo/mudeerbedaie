<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VaultAuditLog;
use App\Models\VaultCategory;
use App\Models\VaultCredential;
use App\Models\VaultTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->nonAdmin = User::factory()->create(['role' => 'user']);
});

// =========================================================================
// Access control
// =========================================================================

test('non-admin cannot access vault dashboard', function () {
    $this->actingAs($this->nonAdmin)
        ->get(route('vault.dashboard'))
        ->assertForbidden();
});

test('non-admin cannot access vault credentials', function () {
    $this->actingAs($this->nonAdmin)
        ->get(route('vault.credentials.index'))
        ->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->get(route('vault.dashboard'))
        ->assertRedirect(route('login'));
});

// =========================================================================
// Dashboard
// =========================================================================

test('admin can view vault dashboard', function () {
    VaultCredential::factory()->count(3)->create();
    $category = VaultCategory::create(['name' => 'Social Media', 'slug' => 'social-media']);
    VaultCredential::factory()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->get(route('vault.dashboard'))
        ->assertOk();
});

// =========================================================================
// Credentials — index
// =========================================================================

test('admin can list credentials', function () {
    VaultCredential::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('vault.credentials.index'))
        ->assertOk();
});

// =========================================================================
// Credentials — create
// =========================================================================

test('admin can create credential with tags and audit log is recorded', function () {
    $category = VaultCategory::create(['name' => 'Email', 'slug' => 'email']);

    $this->actingAs($this->admin)
        ->post(route('vault.credentials.store'), [
            'name' => 'Gmail Admin',
            'url' => 'https://mail.google.com',
            'username' => 'admin@company.com',
            'password' => 'super-secret-123',
            'notes' => 'Main company email',
            'category_id' => $category->id,
            'tags' => ['Production', 'Critical'],
        ])
        ->assertRedirect();

    // Credential was created
    $credential = VaultCredential::where('name', 'Gmail Admin')->first();
    expect($credential)->not->toBeNull();
    expect($credential->username)->toBe('admin@company.com');
    expect($credential->password)->toBe('super-secret-123');
    expect($credential->category_id)->toBe($category->id);
    expect($credential->created_by)->toBe($this->admin->id);

    // Tags were synced (created if they didn't exist)
    expect($credential->tags)->toHaveCount(2);
    expect($credential->tags->pluck('name')->sort()->values()->toArray())
        ->toBe(['Critical', 'Production']);

    // Audit log was created
    $log = VaultAuditLog::where('credential_id', $credential->id)
        ->where('action', 'created')
        ->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->admin->id);
});

test('create credential requires name and password', function () {
    $this->actingAs($this->admin)
        ->post(route('vault.credentials.store'), [
            'name' => '',
            'password' => '',
        ])
        ->assertSessionHasErrors(['name', 'password']);
});

// =========================================================================
// Credentials — show (JSON with decrypted password)
// =========================================================================

test('admin can view credential password as JSON and audit log is recorded', function () {
    $credential = VaultCredential::factory()->create([
        'password' => 'my-secret-pass',
        'notes' => 'Confidential notes',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('vault.credentials.show', $credential))
        ->assertOk()
        ->assertJsonFragment([
            'password' => 'my-secret-pass',
            'notes' => 'Confidential notes',
        ]);

    // Audit log records the view
    $log = VaultAuditLog::where('credential_id', $credential->id)
        ->where('action', 'viewed')
        ->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->admin->id);
});

// =========================================================================
// Credentials — update
// =========================================================================

test('admin can update credential', function () {
    $credential = VaultCredential::factory()->create([
        'name' => 'Old Name',
        'password' => 'old-pass',
    ]);

    $this->actingAs($this->admin)
        ->put(route('vault.credentials.update', $credential), [
            'name' => 'New Name',
            'password' => 'new-pass',
            'tags' => ['Updated'],
        ])
        ->assertRedirect();

    $fresh = $credential->fresh();
    expect($fresh->name)->toBe('New Name');
    expect($fresh->password)->toBe('new-pass');

    // Audit log was created with changes
    $log = VaultAuditLog::where('credential_id', $credential->id)
        ->where('action', 'updated')
        ->first();
    expect($log)->not->toBeNull();
    expect($log->changes)->toBeArray();
});

test('update credential without password does not clear it', function () {
    $credential = VaultCredential::factory()->create([
        'name' => 'Keep Pass',
        'password' => 'keep-this',
    ]);

    $this->actingAs($this->admin)
        ->put(route('vault.credentials.update', $credential), [
            'name' => 'Updated Name',
            // password omitted
        ])
        ->assertRedirect();

    expect($credential->fresh()->password)->toBe('keep-this');
    expect($credential->fresh()->name)->toBe('Updated Name');
});

// =========================================================================
// Credentials — delete
// =========================================================================

test('admin can delete credential and audit log is recorded', function () {
    $credential = VaultCredential::factory()->create();
    $credentialId = $credential->id;

    $this->actingAs($this->admin)
        ->delete(route('vault.credentials.destroy', $credential))
        ->assertRedirect();

    expect(VaultCredential::find($credentialId))->toBeNull();

    // The audit log's credential_id is set to NULL by the FK nullOnDelete
    // cascade when the credential row is removed. Query by action + user
    // instead to verify the log was recorded.
    $log = VaultAuditLog::where('action', 'deleted')
        ->where('user_id', $this->admin->id)
        ->first();
    expect($log)->not->toBeNull();
});

// =========================================================================
// Categories — CRUD
// =========================================================================

test('admin can list categories', function () {
    VaultCategory::create(['name' => 'Social Media', 'slug' => 'social-media']);
    VaultCategory::create(['name' => 'Email', 'slug' => 'email']);

    $this->actingAs($this->admin)
        ->get(route('vault.categories.index'))
        ->assertOk();
});

test('admin can create category', function () {
    $this->actingAs($this->admin)
        ->post(route('vault.categories.store'), [
            'name' => 'Hosting',
            'icon' => 'server',
            'color' => 'blue',
            'sort_order' => 1,
        ])
        ->assertRedirect();

    $category = VaultCategory::where('name', 'Hosting')->first();
    expect($category)->not->toBeNull();
    expect($category->icon)->toBe('server');
    expect($category->color)->toBe('blue');
    expect($category->sort_order)->toBe(1);
});

test('admin can update category', function () {
    $category = VaultCategory::create(['name' => 'Old', 'slug' => 'old']);

    $this->actingAs($this->admin)
        ->put(route('vault.categories.update', $category), [
            'name' => 'Updated',
            'sort_order' => 5,
        ])
        ->assertRedirect();

    expect($category->fresh()->name)->toBe('Updated');
});

test('admin can delete category', function () {
    $category = VaultCategory::create(['name' => 'Delete Me', 'slug' => 'delete-me']);
    $id = $category->id;

    $this->actingAs($this->admin)
        ->delete(route('vault.categories.destroy', $category))
        ->assertRedirect();

    expect(VaultCategory::find($id))->toBeNull();
});

// =========================================================================
// Tags — CRUD
// =========================================================================

test('admin can list tags', function () {
    VaultTag::create(['name' => 'Production', 'slug' => 'production']);

    $this->actingAs($this->admin)
        ->get(route('vault.tags.index'))
        ->assertOk();
});

test('admin can create tag', function () {
    $this->actingAs($this->admin)
        ->post(route('vault.tags.store'), [
            'name' => 'Staging',
        ])
        ->assertRedirect();

    expect(VaultTag::where('name', 'Staging')->exists())->toBeTrue();
});

test('admin can update tag', function () {
    $tag = VaultTag::create(['name' => 'Old Tag', 'slug' => 'old-tag']);

    $this->actingAs($this->admin)
        ->put(route('vault.tags.update', $tag), [
            'name' => 'Renamed Tag',
        ])
        ->assertRedirect();

    expect($tag->fresh()->name)->toBe('Renamed Tag');
});

test('admin can delete tag', function () {
    $tag = VaultTag::create(['name' => 'Temp', 'slug' => 'temp']);
    $id = $tag->id;

    $this->actingAs($this->admin)
        ->delete(route('vault.tags.destroy', $tag))
        ->assertRedirect();

    expect(VaultTag::find($id))->toBeNull();
});

// =========================================================================
// Audit Log
// =========================================================================

test('admin can view audit log', function () {
    VaultAuditLog::create([
        'user_id' => $this->admin->id,
        'action' => 'created',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($this->admin)
        ->get(route('vault.audit.index'))
        ->assertOk();
});

test('audit log can be filtered by action', function () {
    VaultAuditLog::create(['user_id' => $this->admin->id, 'action' => 'created', 'ip_address' => '127.0.0.1']);
    VaultAuditLog::create(['user_id' => $this->admin->id, 'action' => 'viewed', 'ip_address' => '127.0.0.1']);
    VaultAuditLog::create(['user_id' => $this->admin->id, 'action' => 'deleted', 'ip_address' => '127.0.0.1']);

    $this->actingAs($this->admin)
        ->get(route('vault.audit.index', ['action' => 'viewed']))
        ->assertOk();
});

// =========================================================================
// Audit log never stores plain-text password values
// =========================================================================

test('audit log redacts password and notes in changes', function () {
    $credential = VaultCredential::factory()->create([
        'name' => 'Secret Cred',
        'password' => 'original-pass',
        'notes' => 'original-notes',
    ]);

    $this->actingAs($this->admin)
        ->put(route('vault.credentials.update', $credential), [
            'name' => 'Updated Cred',
            'password' => 'new-secret-pass',
            'notes' => 'new-secret-notes',
        ])
        ->assertRedirect();

    $log = VaultAuditLog::where('credential_id', $credential->id)
        ->where('action', 'updated')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->changes)->toBeArray();

    // Password and notes must be redacted, never stored in plain text
    if (isset($log->changes['old']['password'])) {
        expect($log->changes['old']['password'])->toBe('***');
    }
    if (isset($log->changes['new']['password'])) {
        expect($log->changes['new']['password'])->toBe('***');
    }
    if (isset($log->changes['old']['notes'])) {
        expect($log->changes['old']['notes'])->toBe('***');
    }
    if (isset($log->changes['new']['notes'])) {
        expect($log->changes['new']['notes'])->toBe('***');
    }
});
