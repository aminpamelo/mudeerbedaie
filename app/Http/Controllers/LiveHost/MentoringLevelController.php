<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentoringLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MentoringLevelController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('mentoring/levels/Index', [
            'levels' => LiveHostMentoringLevel::query()
                ->withCount('mentees')
                ->orderBy('position')
                ->get()
                ->map(fn (LiveHostMentoringLevel $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'slug' => $l->slug,
                    'color' => $l->color,
                    'position' => (int) $l->position,
                    'is_top' => (bool) $l->is_top,
                    'description' => $l->description,
                    'min_sessions' => $l->min_sessions,
                    'min_hours' => $l->min_hours !== null ? (float) $l->min_hours : null,
                    'min_gmv_myr' => $l->min_gmv_myr !== null ? (float) $l->min_gmv_myr : null,
                    'min_attendance_pct' => $l->min_attendance_pct,
                    'monthly_sales_target' => $l->monthly_sales_target,
                    'is_active' => (bool) $l->is_active,
                    'mentees_count' => (int) ($l->mentees_count ?? 0),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateLevel($request);

        DB::transaction(function () use ($data): void {
            if (! empty($data['is_top'])) {
                LiveHostMentoringLevel::query()->where('is_top', true)->update(['is_top' => false]);
            }

            LiveHostMentoringLevel::create([
                ...$data,
                'slug' => $this->uniqueSlug($data['name']),
                'position' => ((int) LiveHostMentoringLevel::max('position')) + 1,
            ]);
        });

        return back()->with('success', 'Level added.');
    }

    public function update(Request $request, LiveHostMentoringLevel $level): RedirectResponse
    {
        $data = $this->validateLevel($request);

        DB::transaction(function () use ($data, $level): void {
            if (! empty($data['is_top']) && ! $level->is_top) {
                LiveHostMentoringLevel::query()->where('id', '!=', $level->id)->where('is_top', true)->update(['is_top' => false]);
            }

            $level->update($data);
        });

        return back()->with('success', 'Level updated.');
    }

    public function destroy(Request $request, LiveHostMentoringLevel $level): RedirectResponse
    {
        // FK is nullOnDelete: any mentee currently on this level falls back to
        // "no level assigned" rather than blocking the delete.
        $level->delete();

        return back()->with('success', 'Level removed.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level_ids' => ['required', 'array', 'min:1'],
            'level_ids.*' => ['integer'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach (array_map('intval', $data['level_ids']) as $index => $levelId) {
                LiveHostMentoringLevel::where('id', $levelId)->update(['position' => $index + 1]);
            }
        });

        return back()->with('success', 'Levels reordered.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLevel(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_top' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'min_sessions' => ['nullable', 'integer', 'min:0'],
            'min_hours' => ['nullable', 'numeric', 'min:0'],
            'min_gmv_myr' => ['nullable', 'numeric', 'min:0'],
            'min_attendance_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'monthly_sales_target' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'level';
        $slug = $base;
        $i = 2;
        while (LiveHostMentoringLevel::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
