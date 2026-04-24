<?php

namespace App\Http\Controllers\LiveHost;

use App\Exceptions\Recruitment\InvalidFormSchemaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\Recruitment\CampaignRequest;
use App\Models\LiveHostRecruitmentCampaign;
use App\Services\Recruitment\FormSchemaValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

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

    public function create(Request $request): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        return Inertia::render('recruitment/campaigns/Create', []);
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $campaign = LiveHostRecruitmentCampaign::create($data);

        return redirect()
            ->route('livehost.recruitment.campaigns.edit', $campaign)
            ->with('success', "Campaign \"{$campaign->title}\" created.");
    }

    public function show(Request $request, LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        return redirect()->route('livehost.recruitment.campaigns.edit', $campaign);
    }

    public function edit(Request $request, LiveHostRecruitmentCampaign $campaign): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

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
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        if ($request->has('form_schema')) {
            try {
                (new FormSchemaValidator)->validate($request->input('form_schema'));
            } catch (InvalidFormSchemaException $e) {
                return back()->withErrors(['form_schema' => $e->errors])->withInput();
            }
        }

        $data = $request->validated();
        unset($data['status']); // status goes through lifecycle endpoints

        $campaign->update($data);

        return redirect()
            ->route('livehost.recruitment.campaigns.edit', $campaign)
            ->with('success', 'Campaign updated.');
    }

    public function publish(Request $request, LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        if ($campaign->status !== 'draft') {
            abort(422, 'Only draft campaigns can be published.');
        }

        if (! $campaign->stages()->where('is_final', true)->exists()) {
            abort(422, 'Campaign must have a final stage.');
        }

        try {
            (new FormSchemaValidator)->validate($campaign->form_schema ?? []);
        } catch (InvalidFormSchemaException $e) {
            return back()->withErrors(['form_schema' => $e->errors]);
        }

        $campaign->update(['status' => 'open']);

        return back()->with('success', 'Campaign published.');
    }

    public function pause(Request $request, LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        if ($campaign->status !== 'open') {
            abort(422, 'Only open campaigns can be paused.');
        }

        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'Campaign paused.');
    }

    public function close(Request $request, LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        if (! in_array($campaign->status, ['open', 'paused'], true)) {
            abort(422, 'Only open or paused campaigns can be closed.');
        }

        $campaign->update(['status' => 'closed']);

        return back()->with('success', 'Campaign closed.');
    }

    public function destroy(Request $request, LiveHostRecruitmentCampaign $campaign): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

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
