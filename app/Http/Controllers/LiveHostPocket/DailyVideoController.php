<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $videos = $mentee->dailyVideos()
            ->whereBetween('video_date', [$monthStart->toDateString(), $today->endOfMonth()->toDateString()])
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

        $daysLogged = $videos->groupBy(fn (LiveHostMenteeDailyVideo $v) => $v->video_date->toDateString())->count();

        return Inertia::render('DailyVideos', [
            'enrollment' => [
                'mentee_number' => $mentee->mentee_number,
                'program' => ['title' => $mentee->program?->title],
            ],
            'today' => [
                'date' => $todayKey,
                'label' => $today->format('l, j F'),
                'videos' => $todayVideos->map(fn (LiveHostMenteeDailyVideo $v) => $this->videoDto($v))->values(),
            ],
            'history' => $history,
            'stats' => [
                'logged_today' => $todayVideos->isNotEmpty(),
                'month_label' => $today->format('F'),
                'month_videos' => $videos->count(),
                'month_days' => $daysLogged,
            ],
        ]);
    }

    /**
     * Log one video the host made today (title required, link optional).
     */
    public function store(Request $request): RedirectResponse
    {
        $mentee = $this->activeMentee($request);
        abort_if($mentee === null, 403, 'You are not currently enrolled in a mentoring program.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'url', 'max:2048'],
        ]);

        $mentee->dailyVideos()->create([
            'video_date' => CarbonImmutable::now()->toDateString(),
            'title' => $data['title'],
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

    private function activeMentee(Request $request): ?LiveHostMentee
    {
        return $request->user()->activeMenteeEnrollment()->with('program:id,title')->first();
    }

    /**
     * @return array{id: int, title: string, link: string|null, time_human: string|null}
     */
    private function videoDto(LiveHostMenteeDailyVideo $video): array
    {
        return [
            'id' => $video->id,
            'title' => $video->title,
            'link' => $video->link,
            'time_human' => $video->created_at?->format('g:i A'),
        ];
    }
}
