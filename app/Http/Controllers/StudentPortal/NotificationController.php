<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Student portal notification endpoints (bell badge + dropdown feed).
 * Surfaces all database notifications for the student user.
 */
class NotificationController extends Controller
{
    /** Recent items + unread count for the header bell dropdown. */
    public function feed(Request $request): JsonResponse
    {
        return response()->json([
            'notifications' => $this->collect($request, 20),
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
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'icon' => $n->data['icon'] ?? null,
                'is_read' => $n->read_at !== null,
                'created_human' => $n->created_at?->diffForHumans(),
            ]);
    }

    /**
     * @return MorphMany<DatabaseNotification, User>
     */
    private function unread(Request $request)
    {
        return $request->user()->unreadNotifications();
    }
}
