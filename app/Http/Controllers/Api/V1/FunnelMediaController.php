<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FunnelMediaController extends Controller
{
    /**
     * List all media for a funnel (or global media if no funnel specified).
     */
    public function index(Request $request): JsonResponse
    {
        $funnelUuid = $request->query('funnel_uuid');
        $query = FunnelMedia::query()
            ->forUser(auth()->id())
            ->images()
            ->latest();

        if ($funnelUuid) {
            $funnel = Funnel::where('uuid', $funnelUuid)->first();
            if ($funnel) {
                // Get both funnel-specific and global media
                $query->where(function ($q) use ($funnel) {
                    $q->where('funnel_id', $funnel->id)
                        ->orWhereNull('funnel_id');
                });
            }
        }

        $media = $query->paginate($request->query('per_page', 24));

        return response()->json([
            'data' => $media->map(fn ($m) => $this->formatMedia($m)),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Upload a new media file.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'], // Max 10MB
            'funnel_uuid' => ['nullable', 'string', 'exists:funnels,uuid'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $funnelId = null;

        if ($request->funnel_uuid) {
            $funnel = Funnel::where('uuid', $request->funnel_uuid)->first();
            $funnelId = $funnel?->id;
        }

        // Generate unique filename
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = 'funnel-media/'.date('Y/m').'/'.$filename;

        // Store the file
        Storage::disk('public')->put($path, file_get_contents($file));

        // Get image dimensions
        $dimensions = @getimagesize($file->getPathname());
        $width = $dimensions[0] ?? null;
        $height = $dimensions[1] ?? null;

        // Create media record
        $media = FunnelMedia::create([
            'funnel_id' => $funnelId,
            'user_id' => auth()->id(),
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $request->alt_text,
        ]);

        return response()->json([
            'data' => $this->formatMedia($media),
            'message' => 'File uploaded successfully',
        ], 201);
    }

    /**
     * Update media details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $media = FunnelMedia::where('id', $id)
            ->forUser(auth()->id())
            ->firstOrFail();

        $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $media->update([
            'alt_text' => $request->alt_text,
        ]);

        return response()->json([
            'data' => $this->formatMedia($media),
            'message' => 'Media updated successfully',
        ]);
    }

    /**
     * Delete a media file.
     */
    public function destroy(int $id): JsonResponse
    {
        $media = FunnelMedia::where('id', $id)
            ->forUser(auth()->id())
            ->firstOrFail();

        $media->delete(); // This will also delete the file via model event

        return response()->json([
            'message' => 'Media deleted successfully',
        ]);
    }

    /**
     * Bulk delete media files.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $media = FunnelMedia::whereIn('id', $request->ids)
            ->forUser(auth()->id())
            ->get();

        $deleted = 0;
        foreach ($media as $item) {
            $item->delete();
            $deleted++;
        }

        return response()->json([
            'message' => "{$deleted} files deleted successfully",
        ]);
    }

    /**
     * Format media for API response.
     */
    protected function formatMedia(FunnelMedia $media): array
    {
        return [
            'id' => $media->id,
            'filename' => $media->filename,
            'original_filename' => $media->original_filename,
            'url' => $media->url,
            'thumbnail_url' => $media->thumbnail_url,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'formatted_size' => $media->formatted_size,
            'width' => $media->width,
            'height' => $media->height,
            'alt_text' => $media->alt_text,
            'funnel_id' => $media->funnel_id,
            'created_at' => $media->created_at->toIso8601String(),
        ];
    }
}
