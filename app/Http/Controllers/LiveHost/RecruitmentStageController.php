<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecruitmentStageController extends Controller
{
    public function store(Request $request, LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_final' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($campaign, $data): void {
            $nextPosition = ((int) $campaign->stages()->max('position')) + 1;
            $isFinal = (bool) ($data['is_final'] ?? false);

            if ($isFinal) {
                $campaign->stages()->where('is_final', true)->update(['is_final' => false]);
            }

            $campaign->stages()->create([
                'position' => $nextPosition,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_final' => $isFinal,
            ]);
        });

        return back()->with('success', 'Stage added.');
    }

    public function update(Request $request, LiveHostRecruitmentCampaign $campaign, LiveHostRecruitmentStage $stage): RedirectResponse
    {
        abort_unless($stage->campaign_id === $campaign->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_final' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($campaign, $stage, $data): void {
            $isFinal = (bool) ($data['is_final'] ?? false);

            if ($isFinal && ! $stage->is_final) {
                $campaign->stages()
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

    public function destroy(LiveHostRecruitmentCampaign $campaign, LiveHostRecruitmentStage $stage): RedirectResponse
    {
        abort_unless($stage->campaign_id === $campaign->id, 404);

        if ($stage->applicants()->exists()) {
            abort(422, 'Cannot delete a stage while applicants are on it. Move them to another stage first.');
        }

        if ($stage->is_final && $campaign->stages()->count() > 1) {
            abort(422, 'Cannot delete the only final stage. Mark another stage as final first.');
        }

        $stage->delete();

        return back()->with('success', 'Stage removed.');
    }

    public function reorder(Request $request, LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['integer'],
        ]);

        $ids = array_map('intval', $data['stage_ids']);

        $campaignStageIds = $campaign->stages()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($ids) !== count($campaignStageIds) || array_diff($ids, $campaignStageIds) !== [] || array_diff($campaignStageIds, $ids) !== []) {
            abort(422, 'All campaign stages must be included in the reorder payload.');
        }

        DB::transaction(function () use ($ids, $campaign): void {
            foreach ($ids as $index => $stageId) {
                LiveHostRecruitmentStage::where('id', $stageId)
                    ->where('campaign_id', $campaign->id)
                    ->update(['position' => $index + 1]);
            }
        });

        return back()->with('success', 'Stages reordered.');
    }
}
