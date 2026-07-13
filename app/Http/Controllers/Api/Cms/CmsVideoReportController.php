<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMenteeVideoComment;
use App\Models\User;
use App\Notifications\LiveHost\VideoCommentedNotification;
use App\Services\LiveHost\VideoReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Video Report for the CMS module — the content team monitors the host ×
 * category video matrix and gives feedback on each video, right in the report.
 * Shares the exact read model via {@see VideoReportService}; posting mirrors the
 * Live Host Desk (staff comment + host notification).
 */
class CmsVideoReportController extends Controller
{
    private const MANAGER_ROLES = ['admin', 'admin_livehost'];

    public function __construct(private readonly VideoReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $window = $this->reports->window($request);
        $programs = $this->reports->programs($request);
        $matrix = $this->reports->matrix($programs['selected'], $window);

        return response()->json([
            'programs' => $matrix['programs'],
            'categories' => $matrix['categories'],
            'filters' => [
                'program' => $programs['selectedId'],
                'programOptions' => $programs['all']->map(fn ($p) => ['id' => $p->id, 'title' => $p->title])->values(),
            ],
            'window' => $window['meta'],
        ]);
    }

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

    /** Content team posts feedback on a host's video → notify the host. */
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
