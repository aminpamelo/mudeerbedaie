<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveHostMentoringStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MentoringStageController extends Controller
{
    public function store(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_final' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($program, $data): void {
            $nextPosition = ((int) $program->stages()->max('position')) + 1;
            $isFinal = (bool) ($data['is_final'] ?? false);

            if ($isFinal) {
                $program->stages()->where('is_final', true)->update(['is_final' => false]);
            }

            $program->stages()->create([
                'position' => $nextPosition,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_final' => $isFinal,
            ]);
        });

        return back()->with('success', 'Stage added.');
    }

    public function update(Request $request, LiveHostMentoringProgram $program, LiveHostMentoringStage $stage): RedirectResponse
    {
        abort_unless($stage->program_id === $program->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_final' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($program, $stage, $data): void {
            $isFinal = (bool) ($data['is_final'] ?? false);

            if ($isFinal && ! $stage->is_final) {
                $program->stages()
                    ->where('id', '!=', $stage->id)
                    ->where('is_final', true)
                    ->update(['is_final' => false]);
            }

            $stage->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_final' => $isFinal,
            ]);
        });

        return back()->with('success', 'Stage updated.');
    }

    public function destroy(Request $request, LiveHostMentoringProgram $program, LiveHostMentoringStage $stage): RedirectResponse
    {
        abort_unless($stage->program_id === $program->id, 404);

        if ($stage->mentees()->exists()) {
            abort(422, 'Cannot delete a stage while mentees are on it. Move them to another stage first.');
        }

        if ($stage->is_final && $program->stages()->count() > 1) {
            abort(422, 'Cannot delete the only final stage. Mark another stage as final first.');
        }

        $stage->delete();

        return back()->with('success', 'Stage removed.');
    }

    public function reorder(Request $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        $data = $request->validate([
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['integer'],
        ]);

        $ids = array_map('intval', $data['stage_ids']);

        $programStageIds = $program->stages()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($ids) !== count($programStageIds) || array_diff($ids, $programStageIds) !== [] || array_diff($programStageIds, $ids) !== []) {
            abort(422, 'All program stages must be included in the reorder payload.');
        }

        DB::transaction(function () use ($ids, $program): void {
            foreach ($ids as $index => $stageId) {
                LiveHostMentoringStage::where('id', $stageId)
                    ->where('program_id', $program->id)
                    ->update(['position' => $index + 1]);
            }
        });

        return back()->with('success', 'Stages reordered.');
    }
}
