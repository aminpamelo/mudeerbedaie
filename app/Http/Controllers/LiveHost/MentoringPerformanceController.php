<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyMetric;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMenteeDisciplinaryRecord;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveSession;
use App\Services\Mentoring\MenteeBoardPresenter;
use App\Services\Mentoring\MenteeDailySalesResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MentoringPerformanceController extends Controller
{
    /**
     * Record (or clear) a mentee's monthly Attitude score (0–100) and an optional
     * note. Sales are no longer entered here — the monthly Sales KPI is the SUM of
     * the mentee's effective daily sales (see storeDaily). Upserts on the unique
     * (mentee, year, month). The Overall KPI is computed on read, never stored.
     */
    public function store(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'attitude_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $mentee->monthlyScores()->updateOrCreate(
            ['year' => $data['year'], 'month' => $data['month']],
            [
                'attitude_score' => $data['attitude_score'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()?->id,
            ]
        );

        // Return an Inertia-friendly redirect rather than 204 No Content. The
        // editor saves via Inertia's router.patch; a 204 has no Inertia payload,
        // so Inertia treats it as an invalid response and renders a blank modal.
        return back();
    }

    /**
     * Upsert one day of a mentee's daily performance. The PIC's comment is
     * mandatory (the daily activity-log note); sales_override is optional and,
     * when null, the day falls back to the auto live-session GMV.
     */
    public function storeDaily(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['required', 'string', 'max:5000'],
            'sales_override' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $mentee->dailyMetrics()->updateOrCreate(
            ['metric_date' => CarbonImmutable::parse($data['date'])->startOfDay()],
            [
                'comment' => $data['comment'],
                'sales_override' => $data['sales_override'] ?? null,
                'commented_by' => $request->user()?->id,
                'commented_at' => now(),
            ],
        );

        return back();
    }

    /**
     * Day-by-day breakdown for a mentee in one month — the data behind the
     * expandable horizontal daily strip (auto GMV, override, effective, comment).
     */
    public function dailyBreakdown(Request $request, LiveHostMentee $mentee): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json([
            'mentee_id' => $mentee->id,
            'year' => (int) $data['year'],
            'month' => (int) $data['month'],
            'days' => app(MenteeDailySalesResolver::class)->dailyBreakdown($mentee, (int) $data['year'], (int) $data['month']),
        ]);
    }

    /**
     * Full detail of a single day for one mentee — live sessions (with GMV and
     * timing), the recorded comment, and any disciplinary records on that date.
     * Powers the "what happened this day" section of the day editor modal.
     */
    public function dayDetail(Request $request, LiveHostMentee $mentee): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);
        $date = CarbonImmutable::parse($data['date']);

        $sessions = LiveSession::query()
            ->where('live_host_id', $mentee->mentee_user_id)
            ->whereBetween('scheduled_start_at', [$date->startOfDay(), $date->endOfDay()])
            ->with(['platformAccount:id,name', 'liveAccount:id,nickname'])
            ->orderBy('scheduled_start_at')
            ->get()
            ->map(fn (LiveSession $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'status' => $s->status,
                'account' => $s->liveAccount?->nickname ?: $s->platformAccount?->name,
                'gmv' => $s->status === 'ended' ? round((float) $s->gmv_amount + (float) ($s->gmv_adjustment ?? 0), 2) : null,
                'start' => $s->scheduled_start_at?->format('g:i A'),
                'duration_minutes' => $s->duration_minutes,
            ])->values();

        $disciplinary = $mentee->disciplinaryRecords()
            ->whereDate('incident_date', $date->toDateString())
            ->with('recordedByUser:id,name')
            ->get()
            ->map(fn (LiveHostMenteeDisciplinaryRecord $r) => [
                'id' => $r->id,
                'category' => $r->category,
                'severity' => $r->severity,
                'description' => $r->description,
                'recorded_by' => $r->recordedByUser?->name,
            ])->values();

        $metric = $mentee->dailyMetrics()
            ->whereDate('metric_date', $date->toDateString())
            ->with('commentedByUser:id,name')
            ->first();

        $videos = $mentee->dailyVideos()
            ->whereDate('video_date', $date->toDateString())
            ->orderBy('created_at')
            ->get()
            ->map(fn (LiveHostMenteeDailyVideo $v) => [
                'id' => $v->id,
                'title' => $v->title,
                'link' => $v->link,
            ])->values();

        return response()->json([
            'date' => $date->toDateString(),
            'sessions' => $sessions,
            'disciplinary' => $disciplinary,
            'videos' => $videos,
            'metric' => $metric ? [
                'comment' => $metric->comment,
                'sales_override' => $metric->sales_override !== null ? (float) $metric->sales_override : null,
                'commented_by' => $metric->commentedByUser?->name,
                'commented_at_human' => $metric->commented_at?->diffForHumans(),
            ] : null,
        ]);
    }

    /**
     * A full month overview for one mentee — the whole month, day by day, with
     * live sessions, the daily comment, and disciplinary records, plus month
     * totals and the recorded attitude. Powers the month-detail modal so a PIC
     * can see everything that happened for a host that month in one place.
     */
    public function monthOverview(Request $request, LiveHostMentee $mentee): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $year = (int) $data['year'];
        $month = (int) $data['month'];

        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();
        $daysInMonth = (int) $end->format('j');

        $mentee->loadMissing(['menteeUser:id,name', 'level:id,name,color,monthly_sales_target', 'mentor:id,name']);

        $sessions = LiveSession::query()
            ->where('live_host_id', $mentee->mentee_user_id)
            ->whereBetween('scheduled_start_at', [$start->startOfDay(), $end->endOfDay()])
            ->with(['platformAccount:id,name', 'liveAccount:id,nickname'])
            ->orderBy('scheduled_start_at')
            ->get()
            ->groupBy(fn (LiveSession $s) => $s->scheduled_start_at->toDateString());

        $metrics = $mentee->dailyMetrics()
            ->whereBetween('metric_date', [$start->toDateString(), $end->toDateString()])
            ->with('commentedByUser:id,name')
            ->get()
            ->keyBy(fn (LiveHostMenteeDailyMetric $r) => $r->metric_date->toDateString());

        $disciplinary = $mentee->disciplinaryRecords()
            ->whereBetween('incident_date', [$start->toDateString(), $end->toDateString()])
            ->with('recordedByUser:id,name')
            ->get()
            ->groupBy(fn (LiveHostMenteeDisciplinaryRecord $r) => $r->incident_date->toDateString());

        $videosByDate = $mentee->dailyVideos()
            ->whereBetween('video_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (LiveHostMenteeDailyVideo $r) => $r->video_date->toDateString());

        $salesTotal = 0.0;
        $liveDays = 0;
        $commentDays = 0;
        $videoTotal = 0;
        $videoDays = 0;
        $days = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $start->addDays($d - 1);
            $key = $date->toDateString();
            $daySessions = $sessions[$key] ?? collect();
            $ended = $daySessions->where('status', 'ended');
            $autoGmv = (float) $ended->sum(fn (LiveSession $s) => (float) $s->gmv_amount + (float) ($s->gmv_adjustment ?? 0));
            $metric = $metrics->get($key);
            $override = $metric && $metric->sales_override !== null ? (float) $metric->sales_override : null;
            $effective = round($override ?? $autoGmv, 2);
            $dayDisc = $disciplinary[$key] ?? collect();
            $dayVideos = $videosByDate[$key] ?? collect();

            $salesTotal += $effective;
            if ($ended->isNotEmpty()) {
                $liveDays++;
            }
            if ($metric && $metric->comment) {
                $commentDays++;
            }
            $videoTotal += $dayVideos->count();
            if ($dayVideos->isNotEmpty()) {
                $videoDays++;
            }

            $days[] = [
                'day' => $d,
                'date' => $key,
                'weekday' => $date->format('D'),
                'auto' => round($autoGmv, 2),
                'override' => $override,
                'effective' => $effective,
                'sessions' => $daySessions->map(fn (LiveSession $s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'status' => $s->status,
                    'account' => $s->liveAccount?->nickname ?: $s->platformAccount?->name,
                    'gmv' => $s->status === 'ended' ? round((float) $s->gmv_amount + (float) ($s->gmv_adjustment ?? 0), 2) : null,
                    'start' => $s->scheduled_start_at?->format('g:i A'),
                    'duration_minutes' => $s->duration_minutes,
                ])->values(),
                'comment' => $metric?->comment,
                'commented_by' => $metric?->commentedByUser?->name,
                'commented_at_human' => $metric?->commented_at?->diffForHumans(),
                'disciplinary' => $dayDisc->map(fn (LiveHostMenteeDisciplinaryRecord $r) => [
                    'id' => $r->id,
                    'category' => $r->category,
                    'severity' => $r->severity,
                    'description' => $r->description,
                    'recorded_by' => $r->recordedByUser?->name,
                ])->values(),
                'videos' => $dayVideos->map(fn (LiveHostMenteeDailyVideo $r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'link' => $r->link,
                ])->values(),
                'has_activity' => $daySessions->isNotEmpty() || ($metric && $metric->comment) || $dayDisc->isNotEmpty() || $override !== null || $dayVideos->isNotEmpty(),
            ];
        }

        $score = $mentee->monthlyScores()->where('year', $year)->where('month', $month)->first();

        return response()->json([
            'year' => $year,
            'month' => $month,
            'month_label' => $start->format('F Y'),
            'mentee' => [
                'id' => $mentee->id,
                'name' => $mentee->menteeUser?->name,
                'level' => $mentee->level ? ['name' => $mentee->level->name, 'color' => $mentee->level->color] : null,
                'pic' => ($mentee->mentor ?? $mentee->program?->leader)?->name,
            ],
            'summary' => [
                'sales_total' => round($salesTotal, 2),
                'sales_target' => $mentee->level?->monthly_sales_target,
                'attitude' => $score?->attitude_score,
                'note' => $score?->notes,
                'live_days' => $liveDays,
                'comment_days' => $commentDays,
                'disciplinary_total' => $disciplinary->flatten(1)->count(),
                'video_total' => $videoTotal,
                'video_days' => $videoDays,
                'days_in_month' => $daysInMonth,
            ],
            'days' => $days,
        ]);
    }

    /**
     * The daily matrix for one month across every active/graduated mentee — the
     * data behind expanding a month column inline into per-day columns. Returns
     * each mentee's day-by-day effective sales + comment flags, keyed by mentee id.
     */
    public function dailyMatrix(Request $request, LiveHostMentoringProgram $program): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $year = (int) $data['year'];
        $month = (int) $data['month'];

        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();
        $daysInMonth = (int) $end->format('j');

        $mentees = $program->mentees()
            ->whereIn('status', ['active', 'graduated'])
            ->get(['id', 'mentee_user_id']);

        $resolver = app(MenteeDailySalesResolver::class);
        $auto = $resolver->autoDailyGmv($mentees->pluck('mentee_user_id')->filter()->all(), $start, $end);

        $metricRows = LiveHostMenteeDailyMetric::query()
            ->whereIn('mentee_id', $mentees->pluck('id'))
            ->whereBetween('metric_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('mentee_id');

        $disciplinaryRows = LiveHostMenteeDisciplinaryRecord::query()
            ->whereIn('mentee_id', $mentees->pluck('id'))
            ->whereBetween('incident_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('mentee_id');

        $videoRows = LiveHostMenteeDailyVideo::query()
            ->whereIn('mentee_id', $mentees->pluck('id'))
            ->whereBetween('video_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('mentee_id');

        $byMentee = [];
        foreach ($mentees as $m) {
            $metricByDate = ($metricRows[$m->id] ?? collect())->keyBy(fn ($r) => $r->metric_date->toDateString());
            $discByDate = ($disciplinaryRows[$m->id] ?? collect())->groupBy(fn ($r) => $r->incident_date->toDateString());
            $videoByDate = ($videoRows[$m->id] ?? collect())->groupBy(fn ($r) => $r->video_date->toDateString());

            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = $start->addDays($d - 1)->toDateString();
                $autoGmv = (float) ($auto[$m->mentee_user_id][$date]['gmv'] ?? 0);
                $sessions = (int) ($auto[$m->mentee_user_id][$date]['sessions'] ?? 0);
                $row = $metricByDate->get($date);
                $override = $row && $row->sales_override !== null ? (float) $row->sales_override : null;
                $discCount = ($discByDate[$date] ?? collect())->count();
                $videoCount = ($videoByDate[$date] ?? collect())->count();

                $days[] = [
                    'day' => $d,
                    'date' => $date,
                    'auto' => round($autoGmv, 2),
                    'override' => $override,
                    'effective' => round($override ?? $autoGmv, 2),
                    'sessions' => $sessions,
                    'comment' => $row?->comment,
                    'has_comment' => (bool) ($row && $row->comment),
                    'disciplinary_count' => $discCount,
                    'has_disciplinary' => $discCount > 0,
                    'video_count' => $videoCount,
                    'has_video' => $videoCount > 0,
                ];
            }

            $byMentee[$m->id] = $days;
        }

        return response()->json([
            'year' => $year,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'by_mentee' => $byMentee,
        ]);
    }

    /**
     * The daily-log entry surface: every active mentee's effective sales + comment
     * status for a single date, so the PIC can sweep the whole cohort in one pass
     * and see who is still missing today's mandatory comment.
     */
    public function dailyLog(Request $request, LiveHostMentoringProgram $program): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);
        $date = CarbonImmutable::parse($data['date']);
        $dateKey = $date->toDateString();

        $mentees = $program->mentees()
            ->where('status', 'active')
            ->with(['menteeUser:id,name', 'mentor:id,name', 'level:id,name,color'])
            ->orderByDesc('enrolled_at')
            ->get();

        $resolver = app(MenteeDailySalesResolver::class);
        $auto = $resolver->autoDailyGmv($mentees->pluck('mentee_user_id')->filter()->all(), $date, $date);

        $rows = LiveHostMenteeDailyMetric::query()
            ->whereIn('mentee_id', $mentees->pluck('id'))
            ->whereDate('metric_date', $dateKey)
            ->get()
            ->keyBy('mentee_id');

        $disciplinary = LiveHostMenteeDisciplinaryRecord::query()
            ->whereIn('mentee_id', $mentees->pluck('id'))
            ->whereDate('incident_date', $dateKey)
            ->get()
            ->groupBy('mentee_id');

        $videos = LiveHostMenteeDailyVideo::query()
            ->whereIn('mentee_id', $mentees->pluck('id'))
            ->whereDate('video_date', $dateKey)
            ->orderBy('created_at')
            ->get()
            ->groupBy('mentee_id');

        $leader = $program->leader;
        $leaderData = $leader ? [
            'id' => $leader->id,
            'name' => $leader->name,
            'initials' => MenteeBoardPresenter::initials($leader->name),
        ] : null;

        $out = $mentees->map(function (LiveHostMentee $m) use ($auto, $rows, $disciplinary, $videos, $dateKey, $leaderData) {
            $row = $rows->get($m->id);
            $autoGmv = (float) ($auto[$m->mentee_user_id][$dateKey]['gmv'] ?? 0);
            $sessions = (int) ($auto[$m->mentee_user_id][$dateKey]['sessions'] ?? 0);
            $override = $row && $row->sales_override !== null ? (float) $row->sales_override : null;
            $discCount = ($disciplinary[$m->id] ?? collect())->count();
            $menteeVideos = $videos[$m->id] ?? collect();

            $pic = $m->mentor
                ? ['id' => $m->mentor->id, 'name' => $m->mentor->name, 'initials' => MenteeBoardPresenter::initials($m->mentor->name), 'is_override' => true]
                : ($leaderData ? array_merge($leaderData, ['is_override' => false]) : null);

            return [
                'id' => $m->id,
                'name' => $m->menteeUser?->name,
                'level' => $m->level ? ['id' => $m->level->id, 'name' => $m->level->name, 'color' => $m->level->color] : null,
                'pic' => $pic,
                'auto' => round($autoGmv, 2),
                'sessions' => $sessions,
                'sales_override' => $override,
                'sales' => round($override ?? $autoGmv, 2),
                'comment' => $row?->comment,
                'has_comment' => (bool) ($row && $row->comment),
                'disciplinary_count' => $discCount,
                'has_disciplinary' => $discCount > 0,
                'video_count' => $menteeVideos->count(),
                'has_video' => $menteeVideos->isNotEmpty(),
                'videos' => $menteeVideos->map(fn (LiveHostMenteeDailyVideo $v) => [
                    'id' => $v->id,
                    'title' => $v->title,
                    'link' => $v->link,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'date' => $dateKey,
            'mentees' => $out,
            'missing' => $out->where('has_comment', false)->count(),
            'missing_video' => $out->where('has_video', false)->count(),
        ]);
    }
}
