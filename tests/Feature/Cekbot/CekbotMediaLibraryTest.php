<?php

use App\Models\CekbotMedia;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
});

it('renders the Cekbot media page with keys and their library files', function () {
    CekbotMedia::factory()->create(['key' => 'testimoni-1']);

    test()->actingAs($this->admin)
        ->get(route('cekbot.media'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Media/Index', false)
            ->has('media', 1)
            ->where('media.0.key', 'testimoni-1')
            ->where('media.0.sendable', true));
});

it('lists only WhatsApp-sendable Media Library items in the picker', function () {
    $jpg = Media::factory()->create(['title' => 'Testi JPG', 'file_size' => 400_000]);
    $mp4 = Media::factory()->video()->create(['title' => 'Testi MP4', 'file_size' => 3_000_000]);
    Media::factory()->create(['title' => 'Big JPG', 'file_size' => 9_000_000]);
    Media::factory()->create(['title' => 'Gif', 'mime_type' => 'image/gif', 'file_size' => 100_000]);
    Media::factory()->video()->create(['title' => 'Mov', 'mime_type' => 'video/quicktime', 'file_size' => 1_000_000]);
    Media::factory()->video()->create(['title' => 'Big MP4', 'file_size' => 20_000_000]);
    CekbotMedia::factory()->create(['key' => 'dah-ada', 'media_id' => $jpg->id]);

    $items = test()->actingAs($this->admin)
        ->getJson(route('cekbot.media.library'))
        ->assertOk()
        ->json('items');

    expect(collect($items)->pluck('id')->sort()->values()->all())->toBe(collect([$jpg->id, $mp4->id])->sort()->values()->all())
        ->and(collect($items)->firstWhere('id', $jpg->id)['key'])->toBe('dah-ada');

    test()->actingAs($this->admin)
        ->getJson(route('cekbot.media.library', ['type' => 'video', 'q' => 'Testi']))
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $mp4->id);
});

it('keys an existing Media Library item without copying the file', function () {
    $media = Media::factory()->video()->create(['file_size' => 3_000_000]);

    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), ['media_id' => $media->id, 'key' => 'video-testimoni', 'title' => 'Video'])
        ->assertSessionHasNoErrors();

    $item = CekbotMedia::query()->sole();
    expect($item->media_id)->toBe($media->id)
        ->and($item->isVideo())->toBeTrue()
        ->and(Media::query()->count())->toBe(1);
});

it('refuses a library item WhatsApp cannot send', function () {
    $mov = Media::factory()->video()->create(['mime_type' => 'video/quicktime', 'file_size' => 1_000_000]);

    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), ['media_id' => $mov->id, 'key' => 'mov'])
        ->assertSessionHasErrors('media_id');

    expect(CekbotMedia::query()->count())->toBe(0);
});

it('uploads a new file into the shared Media Library and keys it', function () {
    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), [
            'file' => UploadedFile::fake()->image('testi.jpg'),
            'key' => 'testimoni-1',
            'title' => 'Testimoni Puan Aminah',
        ])
        ->assertSessionHasNoErrors();

    $media = Media::query()->sole();
    expect($media->title)->toBe('Testimoni Puan Aminah')
        ->and($media->type)->toBe('image')
        ->and($media->tags)->toBe(['cekbot'])
        ->and($media->uploader_id)->toBe($this->admin->id)
        ->and(CekbotMedia::query()->sole()->media_id)->toBe($media->id);
    Storage::disk('public')->assertExists($media->file_path);
});

it('rejects uploads WhatsApp cannot send', function (UploadedFile $file) {
    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), ['file' => $file, 'key' => 'fail'])
        ->assertSessionHasErrors('file');

    expect(Media::query()->count())->toBe(0);
})->with([
    'big image' => fn () => UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg'),
    'big video' => fn () => UploadedFile::fake()->create('big.mp4', 17000, 'video/mp4'),
    'webp' => fn () => UploadedFile::fake()->create('a.webp', 100, 'image/webp'),
    'mov' => fn () => UploadedFile::fake()->create('a.mov', 100, 'video/quicktime'),
]);

it('requires a library pick or a file', function () {
    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), ['key' => 'kosong'])
        ->assertSessionHasErrors('file');
});

it('validates the key format and uniqueness', function (string $key) {
    CekbotMedia::factory()->create(['key' => 'testimoni-1']);
    $media = Media::factory()->create(['file_size' => 100_000]);

    test()->actingAs($this->admin)
        ->post(route('cekbot.media.store'), ['media_id' => $media->id, 'key' => $key])
        ->assertSessionHasErrors('key');
})->with(['duplicate' => 'testimoni-1', 'spaces' => 'testi moni', 'uppercase' => 'Testimoni', 'empty' => '']);

it('updates the key, title and description', function () {
    $item = CekbotMedia::factory()->create(['key' => 'lama']);

    test()->actingAs($this->admin)
        ->put(route('cekbot.media.update', $item->id), ['key' => 'baru', 'title' => 'Tajuk', 'description' => 'Bila'])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->only(['key', 'title', 'description']))->toBe(['key' => 'baru', 'title' => 'Tajuk', 'description' => 'Bila']);
});

it('removes the key but keeps the file in the Media Library', function () {
    $item = CekbotMedia::factory()->create();

    test()->actingAs($this->admin)->delete(route('cekbot.media.destroy', $item->id))->assertRedirect();

    expect(CekbotMedia::query()->count())->toBe(0)
        ->and(Media::query()->whereKey($item->media_id)->exists())->toBeTrue();
});

it('drops the key when the library file is deleted', function () {
    $item = CekbotMedia::factory()->create();

    $item->media->delete();

    expect(CekbotMedia::query()->count())->toBe(0);
});

it('forbids non-admins from the media library', function () {
    test()->actingAs(User::factory()->create())->get(route('cekbot.media'))->assertForbidden();
    test()->actingAs(User::factory()->create())->getJson(route('cekbot.media.library'))->assertForbidden();
});
