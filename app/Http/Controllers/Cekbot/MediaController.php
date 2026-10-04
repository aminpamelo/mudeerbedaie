<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotMedia;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cekbot keys onto the shared Media Library ({@see CekbotMedia}): pick an
 * existing library image/video (or upload one into the library) and give it a
 * short key the flow AI sends by, e.g. "hantar testimoni-1".
 */
class MediaController extends Controller
{
    private const KEY_RULE = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const MESSAGES = [
        'key.regex' => 'Key hanya huruf kecil, nombor dan sengkang (cth testimoni-1).',
        'key.unique' => 'Key ini sudah digunakan.',
        'file.mimetypes' => 'WhatsApp hanya terima gambar JPG/PNG atau video MP4/3GP.',
        'file.max' => 'Fail terlalu besar. Video maksimum 16MB.',
        'file.required_without' => 'Pilih media dari Media Library atau muat naik fail.',
    ];

    public function index(): Response
    {
        return Inertia::render('Media/Index', [
            'media' => CekbotMedia::query()
                ->with('media')
                ->latest('id')
                ->get()
                ->map(fn (CekbotMedia $item) => $this->shape($item))
                ->values(),
            'limits' => ['imageMb' => CekbotMedia::IMAGE_MAX_BYTES / 1048576, 'videoMb' => CekbotMedia::VIDEO_MAX_BYTES / 1048576],
        ]);
    }

    /**
     * Media Library items WhatsApp can deliver, for the picker.
     */
    public function library(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|in:image,video',
        ]);

        $keysByMedia = CekbotMedia::query()->pluck('key', 'media_id');

        $items = CekbotMedia::constrainToSendable(Media::query())
            ->search($request->query('q'))
            ->ofType($request->query('type'))
            ->latest('id')
            ->limit(60)
            ->get()
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'title' => $media->title ?: $media->original_filename,
                'type' => $media->type,
                'url' => $media->url,
                'size' => $media->file_size,
                'key' => $keysByMedia[$media->id] ?? null,
            ]);

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
            'file' => ['required_without:media_id', 'nullable', 'file', 'mimetypes:'.implode(',', [...CekbotMedia::IMAGE_MIMES, ...CekbotMedia::VIDEO_MIMES]), 'max:'.(CekbotMedia::VIDEO_MAX_BYTES / 1024)],
            'key' => ['required', 'string', 'max:60', self::KEY_RULE, 'unique:cekbot_media,key'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES);

        if (filled($validated['media_id'] ?? null)) {
            $media = CekbotMedia::constrainToSendable(Media::query())->find($validated['media_id']);

            if (! $media) {
                return back()->withErrors(['media_id' => 'Media ini tak boleh dihantar di WhatsApp (format atau saiz). Pilih gambar JPG/PNG ≤5MB atau video MP4 ≤16MB.']);
            }
        } else {
            $file = $request->file('file');

            if (! str_starts_with((string) $file->getMimeType(), 'video/') && $file->getSize() > CekbotMedia::IMAGE_MAX_BYTES) {
                return back()->withErrors(['file' => 'Gambar maksimum 5MB.']);
            }

            $media = $this->storeInLibrary($file, $validated['title'] ?? null);
        }

        CekbotMedia::create([
            'key' => $validated['key'],
            'media_id' => $media->id,
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Media ditambah.');
    }

    public function update(Request $request, CekbotMedia $media): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:60', self::KEY_RULE, Rule::unique('cekbot_media', 'key')->ignore($media->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES);

        $media->update($validated);

        return back()->with('success', 'Media dikemas kini.');
    }

    /**
     * Remove the key only — the file stays in the Media Library.
     */
    public function destroy(CekbotMedia $media): RedirectResponse
    {
        $media->delete();

        return back()->with('success', 'Media dibuang dari Cekbot. Fail masih ada dalam Media Library.');
    }

    /**
     * Save an upload into the shared Media Library, the same way /admin/media does.
     */
    private function storeInLibrary(UploadedFile $file, ?string $title): Media
    {
        $original = $file->getClientOriginalName();
        $fileName = time().'_'.Str::random(6).'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $type = str_starts_with($mime, 'video/') ? 'video' : 'image';
        $size = $type === 'image' ? @getimagesize($file->getRealPath()) : false;

        return Media::create([
            'title' => filled($title) ? $title : Str::headline(pathinfo($original, PATHINFO_FILENAME)),
            'original_filename' => $original,
            'file_name' => $fileName,
            'file_path' => $file->storeAs('media', $fileName, 'public'),
            'disk' => 'public',
            'mime_type' => $mime,
            'type' => $type,
            'file_size' => $file->getSize(),
            'width' => $size ? ($size[0] ?? null) : null,
            'height' => $size ? ($size[1] ?? null) : null,
            'tags' => ['cekbot'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CekbotMedia $item): array
    {
        return [
            'id' => $item->id,
            'key' => $item->key,
            'title' => $item->title ?: $item->media?->title,
            'description' => $item->description,
            'type' => $item->media?->type,
            'url' => $item->url(),
            'size' => $item->media?->file_size,
            'sendable' => $item->isSendable(),
        ];
    }
}
