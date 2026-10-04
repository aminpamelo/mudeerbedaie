<?php

use App\Models\CekbotMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
});

it('renders the media library page', function () {
    CekbotMedia::factory()->create(['key' => 'testimoni-1']);

    test()->actingAs($this->admin)
        ->get(route('cekbot.media'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Media/Index', false)->has('media', 1)->where('media.0.key', 'testimoni-1'));
});

it('uploads an image with a key', function () {
    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), [
            'file' => UploadedFile::fake()->image('testi.jpg'),
            'key' => 'testimoni-1',
            'title' => 'Testimoni Puan Aminah',
            'description' => 'Bila pelanggan ragu-ragu',
        ])
        ->assertSessionHasNoErrors();

    $media = CekbotMedia::query()->sole();
    expect($media->key)->toBe('testimoni-1')
        ->and($media->type)->toBe(CekbotMedia::TYPE_IMAGE)
        ->and($media->created_by)->toBe($this->admin->id);
    Storage::disk('public')->assertExists($media->path);
});

it('uploads a video', function () {
    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), [
            'file' => UploadedFile::fake()->create('testi.mp4', 2048, 'video/mp4'),
            'key' => 'video-testimoni',
        ])
        ->assertSessionHasNoErrors();

    expect(CekbotMedia::query()->sole()->type)->toBe(CekbotMedia::TYPE_VIDEO);
});

it('rejects images over 5MB, videos over 16MB and other file types', function (UploadedFile $file) {
    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), ['file' => $file, 'key' => 'fail'])
        ->assertSessionHasErrors('file');

    expect(CekbotMedia::query()->count())->toBe(0);
})->with([
    'big image' => fn () => UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg'),
    'big video' => fn () => UploadedFile::fake()->create('big.mp4', 17000, 'video/mp4'),
    'pdf' => fn () => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
]);

it('validates the key format and uniqueness', function (string $key) {
    CekbotMedia::factory()->create(['key' => 'testimoni-1']);

    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), ['file' => UploadedFile::fake()->image('a.jpg'), 'key' => $key])
        ->assertSessionHasErrors('key');
})->with(['duplicate' => 'testimoni-1', 'spaces' => 'testi moni', 'uppercase' => 'Testimoni', 'empty' => '']);

it('updates the key, title and description', function () {
    $media = CekbotMedia::factory()->create(['key' => 'lama']);

    test()->actingAs($this->admin)
        ->put(route('cekbot.media.update', $media->id), ['key' => 'baru', 'title' => 'Tajuk', 'description' => 'Bila'])
        ->assertSessionHasNoErrors();

    expect($media->fresh()->only(['key', 'title', 'description']))->toBe(['key' => 'baru', 'title' => 'Tajuk', 'description' => 'Bila']);
});

it('deletes the media and its file', function () {
    $path = UploadedFile::fake()->image('a.jpg')->store(CekbotMedia::DIRECTORY, 'public');
    $media = CekbotMedia::factory()->create(['path' => $path]);

    test()->actingAs($this->admin)->delete(route('cekbot.media.destroy', $media->id))->assertRedirect();

    expect(CekbotMedia::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

it('forbids non-admins from the media library', function () {
    test()->actingAs(User::factory()->create())->get(route('cekbot.media'))->assertForbidden();
});
