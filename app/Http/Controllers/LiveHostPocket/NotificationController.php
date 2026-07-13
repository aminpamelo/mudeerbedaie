<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The host-facing (Pocket) in-app notification center. Surfaces the database
 * notifications written by the App\Notifications\LiveHost\* classes — video
 * feedback, recap reminders, schedule changes — so hosts see them even without
 * (or before) granting web-push permission.
 */
class NotificationController extends Controller
{
    /** Only Pocket (host-facing) notifications belong in this feed. */
    private const NAMESPACE = 'App\\Notifications\\LiveHost\\%';

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
            ->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notifikasi',
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
