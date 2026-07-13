<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CmsContentTalentController extends Controller
{
    /**
     * Typeahead of live hosts for the talent picker.
     */
    public function hosts(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('search', ''));

        $hosts = User::query()
            ->where('role', 'live_host')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit($request->integer('per_page', 10))
            ->get(['id', 'name', 'email', 'avatar_path'])
            ->each->append('avatar_url');

        return response()->json(['data' => $hosts]);
    }

    /**
     * Attach a live host as talent for this content.
     */
    public function store(Request $request, Content $content): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $isLiveHost = User::where('id', $validated['user_id'])
            ->where('role', 'live_host')
            ->exists();

        if (! $isLiveHost) {
            return response()->json([
                'message' => 'The selected user is not a live host.',
            ], 422);
        }

        $content->talents()->syncWithoutDetaching([$validated['user_id']]);

        return response()->json([
            'data' => $this->talents($content),
            'message' => 'Talent added successfully.',
        ], 201);
    }

    /**
     * Detach a live host from this content's talent.
     */
    public function destroy(Content $content, User $user): JsonResponse
    {
        $content->talents()->detach($user->id);

        return response()->json([
            'data' => $this->talents($content),
            'message' => 'Talent removed successfully.',
        ]);
    }

    /**
     * Fresh talent list for the given content, with avatar URLs resolved.
     */
    protected function talents(Content $content): Collection
    {
        return $content->talents()
            ->get(['users.id', 'name', 'avatar_path'])
            ->each->append('avatar_url')
            ->values();
    }
}
