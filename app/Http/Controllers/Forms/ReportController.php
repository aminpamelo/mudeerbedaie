<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;
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
