<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleFighterInertiaRequests;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Fighter portal's in-app notification center. Surfaces the database
 * notifications written by App\Notifications\Fighter\* (new-order alerts) so a
 * fighter sees them in the bell + feed.
 */
class NotificationController extends Controller
{
    /** Only Fighter notifications belong in this feed. */
    private const NAMESPACE = HandleFighterInertiaRequests::NOTIFICATION_NAMESPACE.'%';

    /** Full history page (bell → "See all"). */
    public function index(Request $request): Response
    {
        return Inertia::render('Notifications', [
            'notifications' => $this->collect($request, 60),
            'unreadCount' => $this->unread($request)->count(),
        ]);
    }

    /** Recent items + unread count for the header bell dropdown. */
    public function feed(Request $request): JsonResponse
    {
        return response()->json([
            'notifications' => $this->collect($request, 15),
            'unread_count' => $this->unread($request)->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->unread($request)->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->unread($request)->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collect(Request $request, int $limit): Collection
    {
        return $request->user()
            ->notifications()
            ->where('type', 'like', self::NAMESPACE)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'is_read' => $n->read_at !== null,
                'created_human' => $n->created_at?->diffForHumans(),
            ]);
    }

    /**
     * @return MorphMany<DatabaseNotification, User>
     */
    private function unread(Request $request)
    {
        return $request->user()
            ->unreadNotifications()
            ->where('type', 'like', self::NAMESPACE);
    }
}
