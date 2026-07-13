<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMenteeVideoComment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Daily Video log.
 *
 * The host's own surface for logging the video(s) they made today: a title and
 * an optional link (not an upload). Making a daily video is a mentoring KPI, so
 * this is scoped to the host's active mentee enrollment; hosts not currently in
 * a program see an empty state. Multiple videos per day are allowed — daily
 * compliance is simply "at least one video logged today".
 */
class DailyVideoController extends Controller
{
    public function index(Request $request): Response
    {
        $mentee = $this->activeMentee($request);

        if ($mentee === null) {
            return Inertia::render('DailyVideos', [
                'enrollment' => null,
                'today' => null,
                'history' => [],
                'stats' => null,
            ]);
        }

        $today = CarbonImmutable::now();
        $monthStart = $today->startOfMonth();

        // A notification can deep-link to a video from an earlier month; widen the
        // window back to that video's month so the thread is reachable here.
        $rangeStart = $monthStart;
        $focusVideoId = $request->integer('video') ?: null;
        if ($focusVideoId) {
            $focus = $mentee->dailyVideos()->whereKey($focusVideoId)->first(['id', 'video_date']);
            if ($focus && $focus->video_date->lt($rangeStart)) {
                $rangeStart = $focus->video_date->startOfMonth();
            }
        }

        $videos = $mentee->dailyVideos()
            ->with(['comments.user:id,name'])
            ->whereBetween('video_date', [$rangeStart->toDateString(), $today->endOfMonth()->toDateString()])
            ->get();

        $todayKey = $today->toDateString();
        $todayVideos = $videos->filter(fn (LiveHostMenteeDailyVideo $v) => $v->video_date->toDateString() === $todayKey);

        $history = $videos
            ->filter(fn (LiveHostMenteeDailyVideo $v) => $v->video_date->toDateString() !== $todayKey)
            ->groupBy(fn (LiveHostMenteeDailyVideo $v) => $v->video_date->toDateString())
            ->map(fn ($group, $date) => [
                'date' => $date,
                'date_human' => CarbonImmutable::parse($date)->format('D, M j'),
                'count' => $group->count(),
                'videos' => $group->map(fn (LiveHostMenteeDailyVideo $v) => $this->videoDto($v))->values(),
            ])
            ->sortByDesc('date')
            ->values();

        // Compliance stats stay scoped to the current month even if the window
        // was widened for a deep link.
        $monthVideos = $videos->filter(fn (LiveHostMenteeDailyVideo $v) => $v->video_date->gte($monthStart));
        $daysLogged = $monthVideos->groupBy(fn (LiveHostMenteeDailyVideo $v) => $v->video_date->toDateString())->count();

        return Inertia::render('DailyVideos', [
            'enrollment' => [
                'mentee_number' => $mentee->mentee_number,
                'program' => ['title' => $mentee->program?->title],
            ],
            'categories' => collect(LiveHostMenteeDailyVideo::CATEGORIES)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'today' => [
                'date' => $todayKey,
                'label' => $today->format('l, j F'),
                'videos' => $todayVideos->map(fn (LiveHostMenteeDailyVideo $v) => $this->videoDto($v))->values(),
            ],
            'history' => $history,
            'stats' => [
                'logged_today' => $todayVideos->isNotEmpty(),
                'month_label' => $today->format('F'),
                'month_videos' => $monthVideos->count(),
                'month_days' => $daysLogged,
            ],
            'focusVideoId' => $focusVideoId,
        ]);
    }

    /**
     * Log one video the host made (title + category required, date defaults to
     * today but the host may backfill an earlier day, link optional).
     */
    public function store(Request $request): RedirectResponse
    {
        $mentee = $this->activeMentee($request);
        abort_if($mentee === null, 403, 'You are not currently enrolled in a mentoring program.');

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(LiveHostMenteeDailyVideo::CATEGORIES))],
            'link' => ['nullable', 'string', 'url', 'max:2048'],
        ]);

        $mentee->dailyVideos()->create([
            'video_date' => CarbonImmutable::parse($data['date'])->toDateString(),
            'title' => $data['title'],
            'category' => $data['category'],
            'link' => $data['link'] ?? null,
            'logged_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Video logged.');
    }

    /**
     * Remove a video the host logged. Only the owning host may delete it.
     */
    public function destroy(Request $request, LiveHostMenteeDailyVideo $video): RedirectResponse
    {
        abort_unless($video->mentee?->mentee_user_id === $request->user()->id, 403);

        $video->delete();

        return back()->with('success', 'Video removed.');
    }

    /**
     * Host replies on the two-way feedback thread for one of their own videos.
     */
    public function storeComment(Request $request, LiveHostMenteeDailyVideo $video): RedirectResponse
    {
        abort_unless($video->mentee?->mentee_user_id === $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $video->comments()->create([
            'user_id' => $request->user()->id,
            'author_role' => 'host',
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Reply sent.');
    }

    private function activeMentee(Request $request): ?LiveHostMentee
    {
        return $request->user()->activeMenteeEnrollment()->with('program:id,title')->first();
    }

    /**
     * @return array{id: int, title: string, category: string|null, category_label: string|null, link: string|null, time_human: string|null, comments: array<int, mixed>, unread_feedback: bool}
     */
    private function videoDto(LiveHostMenteeDailyVideo $video): array
    {
        $comments = $video->relationLoaded('comments')
            ? $video->comments->sortBy('created_at')->values()
            : collect();

        return [
            'id' => $video->id,
            'title' => $video->title,
            'category' => $video->category,
            'category_label' => $video->categoryLabel(),
            'link' => $video->link,
            'time_human' => $video->created_at?->format('g:i A'),
            'comments' => $comments->map(fn (LiveHostMenteeVideoComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'is_host' => $c->isFromHost(),
                'author' => $c->isFromHost() ? 'Anda' : ($c->user?->name ?? 'Mentor'),
                'created_human' => $c->created_at?->diffForHumans(),
            ])->values(),
            // The latest comment came from staff and hasn't been replied to yet.
            'unread_feedback' => $comments->isNotEmpty() && ! $comments->last()->isFromHost(),
        ];
    }
}
