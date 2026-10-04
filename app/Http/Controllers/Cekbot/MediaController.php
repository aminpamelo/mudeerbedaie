<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared Cekbot media library ({@see CekbotMedia}): images and videos such as
 * testimonials that the flow AI sends mid-conversation, referenced in flow
 * instructions by their short key.
 */
class MediaController extends Controller
{
    /** WhatsApp's limits: images 5 MB, videos 16 MB. */
    private const IMAGE_MAX_KB = 5120;

    private const VIDEO_MAX_KB = 16384;

    private const KEY_RULE = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function index(): Response
    {
        return Inertia::render('Media/Index', [
            'media' => CekbotMedia::query()
                ->latest('id')
                ->get()
                ->map(fn (CekbotMedia $media) => $this->shape($media))
                ->values(),
            'limits' => ['imageMb' => self::IMAGE_MAX_KB / 1024, 'videoMb' => self::VIDEO_MAX_KB / 1024],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/3gpp', 'max:'.self::VIDEO_MAX_KB],
            'key' => ['required', 'string', 'max:60', self::KEY_RULE, 'unique:cekbot_media,key'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'key.regex' => 'Key hanya huruf kecil, nombor dan sengkang (cth testimoni-1).',
            'key.unique' => 'Key ini sudah digunakan.',
            'file.mimetypes' => 'Hanya gambar (JPG/PNG/WEBP) atau video (MP4/3GP).',
            'file.max' => 'Fail terlalu besar. Video maksimum 16MB.',
        ]);

        $file = $request->file('file');
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

        if (! $isVideo && $file->getSize() > self::IMAGE_MAX_KB * 1024) {
            return back()->withErrors(['file' => 'Gambar maksimum 5MB.']);
        }

        CekbotMedia::create([
            'key' => $validated['key'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'type' => $isVideo ? CekbotMedia::TYPE_VIDEO : CekbotMedia::TYPE_IMAGE,
            'path' => $file->store(CekbotMedia::DIRECTORY, 'public'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Media dimuat naik.');
    }

    public function update(Request $request, CekbotMedia $media): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:60', self::KEY_RULE, Rule::unique('cekbot_media', 'key')->ignore($media->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'key.regex' => 'Key hanya huruf kecil, nombor dan sengkang (cth testimoni-1).',
            'key.unique' => 'Key ini sudah digunakan.',
        ]);

        $media->update($validated);

        return back()->with('success', 'Media dikemas kini.');
    }

    public function destroy(CekbotMedia $media): RedirectResponse
    {
        Storage::disk('public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Media dipadam.');
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CekbotMedia $media): array
    {
        return [
            'id' => $media->id,
            'key' => $media->key,
            'title' => $media->title,
            'description' => $media->description,
            'type' => $media->type,
            'url' => $media->url(),
            'size' => $media->size,
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }
}
