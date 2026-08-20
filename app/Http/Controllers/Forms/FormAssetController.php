<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Handles image uploads for the Forms builder — header logos and inline image
 * blocks. Returns the stored relative path plus a public URL so the builder can
 * preview immediately and persist the path inside the form/field settings.
 */
class FormAssetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:4096'],
        ]);

        $path = $request->file('image')->store(
            'forms/assets/'.$request->user()->id,
            'public',
        );

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
