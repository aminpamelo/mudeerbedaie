<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * In-app analytics for a single form — response totals, a 30-day trend, and a
 * per-question breakdown (choice tallies, rating distributions, number stats,
 * text response counts). Replaces the export-then-analyse workflow.
 */
class ReportController extends Controller
{
    /**
     * System-wide submissions report across every form — headline KPIs, a
     * submissions trend, the most-answered forms, and per-category / per-status
     * breakdowns. Admin-only overview complementing the per-form report at
     * {form}/report. Filterable by form, category, and date range.
     */
    public function overview(Request $request): Response
    {
        $formId = $request->integer('form') ?: null;
        $categoryId = $request->integer('category') ?: null;
        $from = $request->query('from');
        $to = $request->query('to');
        $hasDateFilter = filled($from) || filled($to);

        $end = filled($to) ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();
        $start = filled($from) ? Carbon::parse($from)->startOfDay() : $end->copy()->subDays(29)->startOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }
        if ($start->diffInDays($end) > 365) {
            $start = $end->copy()->subDays(365)->startOfDay();
        }

        /** @var Collection<int, Form> $forms */
        $forms = Form::query()
            ->with('category:id,name,color')
            ->when($formId, fn ($q) => $q->where('id', $formId))
            ->when($categoryId, fn ($q) => $q->where('form_category_id', $categoryId))
            ->get(['id', 'title', 'status', 'form_category_id', 'user_id']);

        $formIds = $forms->pluck('id');

        /** @var Collection<int, FormSubmission> $submissions */
        $submissions = FormSubmission::query()
            ->whereIn('form_id', $formIds)
            ->when($hasDateFilter, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->get(['form_id', 'created_at']);

        $now = Carbon::now();
        $last7 = $submissions->filter(fn (FormSubmission $s): bool => $s->created_at?->gte($now->copy()->subDays(7)) ?? false)->count();
        $today = $submissions->filter(fn (FormSubmission $s): bool => $s->created_at?->isToday() ?? false)->count();
        $inWindow = $submissions->filter(fn (FormSubmission $s): bool => $s->created_at?->betweenIncluded($start, $end) ?? false)->count();
        $rangeDays = max(1, $start->diffInDays($end) + 1);

        return Inertia::render('Reports', [
            'stats' => [
                'total_forms' => $forms->count(),
                'published' => $forms->where('status', Form::STATUS_PUBLISHED)->count(),
                'total_submissions' => $submissions->count(),
                'creators' => $forms->pluck('user_id')->unique()->count(),
                'last7' => $last7,
                'today' => $today,
                'avg_per_day' => round($inWindow / $rangeDays, 1),
            ],
            'timeseries' => $this->rangedTimeseries($submissions, $start->copy(), $end->copy()),
            'top_forms' => $this->topFormsFromSubmissions($forms, $submissions),
            'categories' => $this->categoryFromSubmissions($forms, $submissions),
            'statuses' => $this->statusBreakdown($forms),
            'filters' => [
                'form' => $formId,
                'category' => $categoryId,
                'from' => $from,
                'to' => $to,
            ],
            'range_label' => $hasDateFilter
                ? $start->translatedFormat('d M Y').' — '.$end->translatedFormat('d M Y')
                : '30 Hari',
            'form_options' => Form::query()->orderBy('title')->get(['id', 'title'])->all(),
            'category_options' => FormCategory::query()->ordered()->get(['id', 'name'])->all(),
        ]);
    }

    /**
     * Submissions per day across an arbitrary [start, end] window (zero-filled).
     *
     * @param  Collection<int, FormSubmission>  $submissions
     * @return array<int, array{date: string, count: int}>
     */
    private function rangedTimeseries(Collection $submissions, Carbon $start, Carbon $end): array
    {
        $counts = $submissions
            ->groupBy(fn (FormSubmission $s): string => $s->created_at?->toDateString() ?? '')
            ->map(fn (Collection $group): int => $group->count());

        $series = [];
        foreach (CarbonPeriod::create($start->startOfDay(), $end->startOfDay()) as $day) {
            $key = $day->toDateString();
            $series[] = ['date' => $key, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $series;
    }

    /**
     * Forms ranked by scoped submission volume, capped for the leaderboard.
     *
     * @param  Collection<int, Form>  $forms
     * @param  Collection<int, FormSubmission>  $submissions
     * @return array<int, array<string, mixed>>
     */
    private function topFormsFromSubmissions(Collection $forms, Collection $submissions): array
    {
        $formsById = $forms->keyBy('id');

        $rows = $submissions
            ->groupBy('form_id')
            ->map(function (Collection $group, $formId) use ($formsById): ?array {
                $form = $formsById->get($formId);
                if (! $form) {
                    return null;
                }

                return [
                    'id' => $form->id,
                    'title' => $form->title,
                    'status' => $form->status,
                    'submissions' => $group->count(),
                ];
            })
            ->filter()
            ->sortByDesc('submissions')
            ->take(8)
            ->values();

        $max = max(1, (int) $rows->max('submissions'));

        return $rows->map(fn (array $row): array => [
            ...$row,
            'pct' => (int) round($row['submissions'] / $max * 100),
        ])->all();
    }

    /**
     * Scoped submission totals grouped by the parent form's category.
     *
     * @param  Collection<int, Form>  $forms
     * @param  Collection<int, FormSubmission>  $submissions
     * @return array<int, array<string, mixed>>
     */
    private function categoryFromSubmissions(Collection $forms, Collection $submissions): array
    {
        $formsById = $forms->keyBy('id');

        $rows = $submissions
            ->groupBy(fn (FormSubmission $s): string => $formsById->get($s->form_id)?->category?->name ?? '__none__')
            ->map(function (Collection $group) use ($formsById): array {
                $category = $formsById->get($group->first()->form_id)?->category;

                return [
                    'name' => $category?->name ?? 'Tanpa Kategori',
                    'color' => $category?->color,
                    'submissions' => $group->count(),
                ];
            })
            ->sortByDesc('submissions')
            ->values();

        $max = max(1, (int) $rows->max('submissions'));

        return $rows->map(fn (array $row): array => [
            ...$row,
            'pct' => (int) round($row['submissions'] / $max * 100),
        ])->all();
    }

    /**
     * Form counts by lifecycle status within the scoped form set.
     *
     * @param  Collection<int, Form>  $forms
     * @return array<int, array{status: string, label: string, count: int, pct: int}>
     */
    private function statusBreakdown(Collection $forms): array
    {
        $labels = [
            Form::STATUS_PUBLISHED => 'Diterbitkan',
            Form::STATUS_DRAFT => 'Draf',
            Form::STATUS_CLOSED => 'Ditutup',
        ];

        $total = $forms->count();

        $rows = [];
        foreach ($labels as $status => $label) {
            $count = $forms->where('status', $status)->count();
            $rows[] = [
                'status' => $status,
                'label' => $label,
                'count' => $count,
                'pct' => $total > 0 ? (int) round($count / $total * 100) : 0,
            ];
        }

        return $rows;
    }

    public function show(Request $request, Form $form): Response
    {
        $this->authorizeForm($request, $form);

        /** @var Collection<int, FormSubmission> $submissions */
        $submissions = $form->submissions()->latest()->get(['data', 'created_at']);

        $total = $submissions->count();

        return Inertia::render('Report', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'slug' => $form->slug,
                'status' => $form->status,
                'public_url' => $form->publicUrl(),
            ],
            'stats' => $this->stats($submissions, $total),
            'timeseries' => $this->timeseries($submissions),
            'fields' => $this->fieldReports($form, $submissions, $total),
        ]);
    }

    /**
     * @param  Collection<int, FormSubmission>  $submissions
     * @return array<string, mixed>
     */
    private function stats(Collection $submissions, int $total): array
    {
        $now = Carbon::now();
        $last7 = $submissions->filter(fn (FormSubmission $s): bool => $s->created_at?->gte($now->copy()->subDays(7)) ?? false)->count();
        $today = $submissions->filter(fn (FormSubmission $s): bool => $s->created_at?->isToday() ?? false)->count();

        $activeDays = $submissions
            ->map(fn (FormSubmission $s): ?string => $s->created_at?->toDateString())
            ->filter()
            ->unique()
            ->count();

        return [
            'total' => $total,
            'last7' => $last7,
            'today' => $today,
            'avg_per_active_day' => $activeDays > 0 ? round($total / $activeDays, 1) : 0,
            'first_at' => $submissions->last()?->created_at?->toIso8601String(),
            'last_at' => $submissions->first()?->created_at?->toIso8601String(),
        ];
    }

    /**
     * Submissions per day for the last 30 days (zero-filled).
     *
     * @param  Collection<int, FormSubmission>  $submissions
     * @return array<int, array{date: string, count: int}>
     */
    private function timeseries(Collection $submissions): array
    {
        $counts = $submissions
            ->groupBy(fn (FormSubmission $s): string => $s->created_at?->toDateString() ?? '')
            ->map(fn (Collection $group): int => $group->count());

        $series = [];
        $period = CarbonPeriod::create(Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->startOfDay());

        foreach ($period as $day) {
            $key = $day->toDateString();
            $series[] = [
                'date' => $key,
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Per-question aggregates.
     *
     * @param  Collection<int, FormSubmission>  $submissions
     * @return array<int, array<string, mixed>>
     */
    private function fieldReports(Form $form, Collection $submissions, int $total): array
    {
        $reports = [];

        foreach ($form->answerableFields() as $field) {
            $id = $field['id'];
            $type = $field['type'] ?? 'short_text';
            $values = $submissions
                ->map(fn (FormSubmission $s): mixed => $s->data[$id] ?? null);

            $answered = $values->filter(fn ($v): bool => $v !== null && $v !== '' && $v !== [])->count();

            $report = [
                'id' => $id,
                'label' => $field['label'] ?: $id,
                'type' => $type,
                'answered' => $answered,
                'answered_pct' => $total > 0 ? round($answered / $total * 100) : 0,
            ];

            if (in_array($type, ['radio', 'dropdown', 'checkbox'], true)) {
                $report['kind'] = 'choice';
                $report['options'] = $this->choiceBreakdown($field, $values, $type === 'checkbox');
            } elseif ($type === 'rating') {
                $report['kind'] = 'rating';
                $report += $this->ratingBreakdown($field, $values);
            } elseif ($type === 'number') {
                $report['kind'] = 'number';
                $report += $this->numberStats($values);
            } else {
                $report['kind'] = 'text';
                $report['samples'] = $values
                    ->filter(fn ($v): bool => is_string($v) && trim($v) !== '')
                    ->take(5)
                    ->values()
                    ->all();
            }

            $reports[] = $report;
        }

        return $reports;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  Collection<int, mixed>  $values
     * @return array<int, array{label: string, count: int, pct: int}>
     */
    private function choiceBreakdown(array $field, Collection $values, bool $multi): array
    {
        $tally = [];
        foreach (($field['options'] ?? []) as $opt) {
            $tally[(string) $opt] = 0;
        }

        foreach ($values as $value) {
            $picks = $multi ? (is_array($value) ? $value : []) : [$value];
            foreach ($picks as $pick) {
                if ($pick === null || $pick === '') {
                    continue;
                }
                $key = (string) $pick;
                $tally[$key] = ($tally[$key] ?? 0) + 1;
            }
        }

        $max = max(1, ...array_values($tally) ?: [1]);

        $rows = [];
        foreach ($tally as $label => $count) {
            $rows[] = [
                'label' => $label,
                'count' => $count,
                'pct' => (int) round($count / $max * 100),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  Collection<int, mixed>  $values
     * @return array<string, mixed>
     */
    private function ratingBreakdown(array $field, Collection $values): array
    {
        $maxStars = (int) ($field['settings']['max'] ?? 5) ?: 5;
        $nums = $values
            ->map(fn ($v): ?float => is_numeric($v) ? (float) $v : null)
            ->filter(fn ($v): bool => $v !== null);

        $dist = [];
        $peak = 1;
        for ($star = $maxStars; $star >= 1; $star--) {
            $count = $nums->filter(fn ($v): bool => (int) round($v) === $star)->count();
            $peak = max($peak, $count);
            $dist[] = ['star' => $star, 'count' => $count];
        }

        $dist = array_map(fn (array $row): array => [
            ...$row,
            'pct' => (int) round($row['count'] / $peak * 100),
        ], $dist);

        return [
            'max_stars' => $maxStars,
            'average' => $nums->isNotEmpty() ? round($nums->avg(), 2) : null,
            'distribution' => $dist,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array<string, mixed>
     */
    private function numberStats(Collection $values): array
    {
        $nums = $values
            ->map(fn ($v): ?float => is_numeric($v) ? (float) $v : null)
            ->filter(fn ($v): bool => $v !== null);

        if ($nums->isEmpty()) {
            return ['average' => null, 'min' => null, 'max' => null, 'sum' => null];
        }

        return [
            'average' => round($nums->avg(), 2),
            'min' => $nums->min(),
            'max' => $nums->max(),
            'sum' => round($nums->sum(), 2),
        ];
    }

    private function authorizeForm(Request $request, Form $form): void
    {
        abort_unless(
            $form->user_id === $request->user()->id || $request->user()->isAdmin(),
            403,
        );
    }
}
