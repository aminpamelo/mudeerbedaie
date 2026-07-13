<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMenteeVideoComment;
use App\Models\User;
use App\Notifications\LiveHost\VideoCommentedNotification;
use App\Services\LiveHost\VideoReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Video Report — a host × month grid of the videos each mentee logged (each month
 * column expands into day columns, like the Mentoring Overview), with a two-way
 * comment thread on every video. Staff feedback notifies the host in their
 * Pocket; the host can reply, surfacing as an "awaiting reply" marker. The read
 * model is shared with the CMS view via {@see VideoReportService}.
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
            'months' => $matrix['months'],
            'filters' => [
                'program' => $programs['selectedId'],
                'programOptions' => $programs['all']->map(fn ($p) => ['id' => $p->id, 'title' => $p->title])->values(),
            ],
            'window' => $window['meta'],
        ]);
    }

    /** JSON: per-host per-day counts for one month (expands that month column). */
    public function dayMatrix(Request $request): JsonResponse
    {
        $programs = $this->reports->programs($request);

        return response()->json($this->reports->dayMatrix(
            $programs['selected'],
            $request->integer('year') ?: (int) now()->format('Y'),
            max(1, min(12, $request->integer('month') ?: (int) now()->format('n'))),
        ));
    }

    /** JSON: the videos in a matrix cell (a month, a day, or the window). */
    public function cell(Request $request): JsonResponse
    {
        $mentee = LiveHostMentee::query()
            ->with('menteeUser:id,name')
            ->findOrFail($request->integer('mentee'));

        [$start, $end, $label] = $this->resolvePeriod($request);

        return response()->json($this->reports->cell($mentee, $start, $end, $label, $request->user()));
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

    /**
     * Resolve the drawer period from ?date=Y-m-d (one day), ?month=Y-m (one
     * month), or the report window (default).
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolvePeriod(Request $request): array
    {
        if ($date = $request->query('date')) {
            $d = CarbonImmutable::parse($date);

            return [$d->startOfDay()->toDateTimeString(), $d->endOfDay()->toDateTimeString(), $d->format('j M Y')];
        }

        if ($month = $request->query('month')) {
            $d = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();

            return [$d->toDateTimeString(), $d->endOfMonth()->toDateTimeString(), $d->format('M Y')];
        }

        $window = $this->reports->window($request);

        return [$window['start'], $window['end'], $window['meta']['label']];
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
