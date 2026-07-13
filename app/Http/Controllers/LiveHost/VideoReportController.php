<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMenteeVideoComment;
use App\Models\User;
use App\Notifications\LiveHost\VideoCommentedNotification;
use App\Services\LiveHost\VideoReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Video Report — a host × content-category matrix of the videos each mentee has
 * logged, with a two-way comment thread on every video. Staff feedback here
 * notifies the host in their Pocket; the host can reply, which surfaces back as
 * an "awaiting reply" marker on the matrix. The read model is shared with the
 * CMS view via {@see VideoReportService}.
 */
class VideoReportController extends Controller
{
    /** Roles allowed to delete any comment (not just their own). */
    private const MANAGER_ROLES = ['admin', 'admin_livehost'];

    public function __construct(private readonly VideoReportService $reports) {}

    public function index(Request $request): Response
    {
        $window = $this->reports->window($request);
        $programs = $this->reports->programs($request);
        $matrix = $this->reports->matrix($programs['selected'], $window);

        return Inertia::render('mentoring/VideoReport', [
            'programs' => $matrix['programs'],
            'categories' => $matrix['categories'],
            'filters' => [
                'program' => $programs['selectedId'],
                'programOptions' => $programs['all']->map(fn ($p) => ['id' => $p->id, 'title' => $p->title])->values(),
            ],
            'window' => $window['meta'],
        ]);
    }

    /**
     * JSON: the videos in a single matrix cell (host + category over the window),
     * each with its full comment thread. Drives the report drawer.
     */
    public function cell(Request $request): JsonResponse
    {
        $mentee = LiveHostMentee::query()
            ->with('menteeUser:id,name')
            ->findOrFail($request->integer('mentee'));

        return response()->json($this->reports->cell(
            $mentee,
            (string) $request->string('category'),
            $this->reports->window($request),
            $request->user(),
        ));
    }

    /** Staff posts feedback on a video → notify the host. Returns the new thread. */
    public function storeComment(Request $request, LiveHostMenteeDailyVideo $video): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $video->comments()->create([
            'user_id' => $request->user()->id,
            'author_role' => 'staff',
            'body' => $data['body'],
        ]);

        $this->notifyHost($video, $request->user(), $data['body']);

        return response()->json([
            'video' => $this->reports->serializeVideo(
                $video->fresh(['comments.user:id,name,role']),
                $request->user(),
            ),
        ]);
    }

    public function destroyComment(Request $request, LiveHostMenteeVideoComment $comment): JsonResponse
    {
        $user = $request->user();
        $canManage = $comment->user_id === $user->id
            || in_array($user->role, self::MANAGER_ROLES, true);

        abort_unless($canManage, 403);

        $comment->delete();

        return response()->json(['ok' => true]);
    }

    private function notifyHost(LiveHostMenteeDailyVideo $video, User $author, string $excerpt): void
    {
        $video->loadMissing('mentee.menteeUser');
        $host = $video->mentee?->menteeUser;

        if ($host && $host->id !== $author->id) {
            $host->notify(new VideoCommentedNotification($video, $author, $excerpt));
        }
    }
}
