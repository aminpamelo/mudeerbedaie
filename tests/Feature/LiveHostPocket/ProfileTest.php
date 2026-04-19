<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('renders the profile page for a live host with a formatted role', function () {
    $host = User::factory()->create([
        'role' => 'live_host',
        'name' => 'Wan Azman',
        'email' => 'wan@example.com',
    ]);

    actingAs($host)
        ->get('/live-host/me')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Profile', false)
            ->where('profile.name', 'Wan Azman')
            ->where('profile.email', 'wan@example.com')
            ->where('profile.role', 'Live Host')
            ->where('profile.avatarUrl', null));
});

it('forbids a non-live-host from viewing the pocket profile', function () {
    $user = User::factory()->create(['role' => 'student']);

    actingAs($user)
        ->get('/live-host/me')
        ->assertForbidden();
});

it('requires auth to view the profile page', function () {
    $this->get('/live-host/me')
        ->assertRedirect('/login');
});

it('uploads an avatar and stores it under user-avatars on the public disk', function () {
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post('/live-host/me/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])
        ->assertRedirect();

    $host->refresh();

    expect($host->avatar_path)->not->toBeNull();
    expect($host->avatar_path)->toStartWith('user-avatars/');
    Storage::disk('public')->assertExists($host->avatar_path);
});

it('deletes the old avatar file when uploading a new one', function () {
    Storage::fake('public');

    $host = User::factory()->create([
        'role' => 'live_host',
        'avatar_path' => 'user-avatars/old.jpg',
    ]);
    Storage::disk('public')->put('user-avatars/old.jpg', 'old');

    actingAs($host)
        ->post('/live-host/me/avatar', [
            'avatar' => UploadedFile::fake()->image('new.jpg', 400, 400),
        ])
        ->assertRedirect();

    Storage::disk('public')->assertMissing('user-avatars/old.jpg');
    Storage::disk('public')->assertExists($host->fresh()->avatar_path);
});

it('removes the stored avatar file and nulls the column', function () {
    Storage::fake('public');

    $host = User::factory()->create([
        'role' => 'live_host',
        'avatar_path' => 'user-avatars/current.jpg',
    ]);
    Storage::disk('public')->put('user-avatars/current.jpg', 'current');

    actingAs($host)
        ->delete('/live-host/me/avatar')
        ->assertRedirect();

    Storage::disk('public')->assertMissing('user-avatars/current.jpg');
    expect($host->fresh()->avatar_path)->toBeNull();
});

it('rejects non-image uploads', function () {
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post('/live-host/me/avatar', [
            'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($host->fresh()->avatar_path)->toBeNull();
});

it('rejects avatar uploads larger than 2MB', function () {
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post('/live-host/me/avatar', [
            'avatar' => UploadedFile::fake()->image('huge.jpg')->size(3072),
        ])
        ->assertSessionHasErrors('avatar');

    expect($host->fresh()->avatar_path)->toBeNull();
});
