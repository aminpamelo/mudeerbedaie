<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\Recruitment\CampaignRequest;
use App\Models\LiveHostRecruitmentCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $campaigns = LiveHostRecruitmentCampaign::query()
            ->withCount('applicants')
            ->latest()
            ->paginate(20)
            ->through(fn (LiveHostRecruitmentCampaign $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'status' => $c->status,
                'applicants_count' => (int) ($c->applicants_count ?? 0),
                'target_count' => $c->target_count,
                'opens_at' => $c->opens_at?->toIso8601String(),
                'closes_at' => $c->closes_at?->toIso8601String(),
                'public_url' => $c->status === 'open' ? route('recruitment.show', $c->slug) : null,
            ]);

        return Inertia::render('recruitment/campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('recruitment/campaigns/Create', []);
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $campaign = LiveHostRecruitmentCampaign::create($data);

        return redirect()
            ->route('livehost.recruitment.campaigns.edit', $campaign)
            ->with('success', "Campaign \"{$campaign->title}\" created.");
    }

    public function show(LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        return redirect()->route('livehost.recruitment.campaigns.edit', $campaign);
    }

    public function edit(LiveHostRecruitmentCampaign $campaign): Response
    {
        $campaign->loadCount('applicants');

        return Inertia::render('recruitment/campaigns/Edit', [
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'description' => $campaign->description,
                'status' => $campaign->status,
                'target_count' => $campaign->target_count,
                'opens_at' => $campaign->opens_at?->toIso8601String(),
                'closes_at' => $campaign->closes_at?->toIso8601String(),
                'applicants_count' => (int) ($campaign->applicants_count ?? 0),
                'public_url' => $campaign->status === 'open' ? route('recruitment.show', $campaign->slug) : null,
            ],
            'stages' => $campaign->stages()->orderBy('position')->get()->map(fn ($s) => [
                'id' => $s->id,
                'position' => $s->position,
                'name' => $s->name,
                'description' => $s->description,
                'is_final' => (bool) $s->is_final,
                'applicants_count' => $s->applicants()->count(),
            ])->values(),
        ]);
    }

    public function update(CampaignRequest $request, LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        $data = $request->validated();
        unset($data['status']); // status goes through lifecycle endpoints

        $campaign->update($data);

        return redirect()
            ->route('livehost.recruitment.campaigns.edit', $campaign)
            ->with('success', 'Campaign updated.');
    }

    public function publish(LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        if ($campaign->status !== 'draft') {
            abort(422, 'Only draft campaigns can be published.');
        }

        if (! $campaign->stages()->where('is_final', true)->exists()) {
            abort(422, 'Campaign must have a final stage.');
        }

        $campaign->update(['status' => 'open']);

        return back()->with('success', 'Campaign published.');
    }

    public function pause(LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        if ($campaign->status !== 'open') {
            abort(422, 'Only open campaigns can be paused.');
        }

        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'Campaign paused.');
    }

    public function close(LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        if (! in_array($campaign->status, ['open', 'paused'], true)) {
            abort(422, 'Only open or paused campaigns can be closed.');
        }

        $campaign->update(['status' => 'closed']);

        return back()->with('success', 'Campaign closed.');
    }

    public function destroy(LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        if ($campaign->applicants()->count() > 0) {
            abort(422, 'Cannot delete a campaign that already has applicants.');
        }

        $title = $campaign->title;
        $campaign->delete();

        return redirect()
            ->route('livehost.recruitment.campaigns.index')
            ->with('success', "Campaign \"{$title}\" deleted.");
    }
}
