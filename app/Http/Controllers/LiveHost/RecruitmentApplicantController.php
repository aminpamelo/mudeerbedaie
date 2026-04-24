<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentApplicantController extends Controller
{
    public function index(Request $request): Response
    {
        $campaignId = $request->integer('campaign') ?: null;

        $campaign = $campaignId
            ? LiveHostRecruitmentCampaign::find($campaignId)
            : LiveHostRecruitmentCampaign::where('status', 'open')->oldest('created_at')->first()
                ?? LiveHostRecruitmentCampaign::latest('created_at')->first();

        $statusTab = $request->input('status', 'active');
        if (! in_array($statusTab, ['active', 'rejected', 'hired'], true)) {
            $statusTab = 'active';
        }

        $applicants = $campaign
            ? LiveHostApplicant::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', $statusTab)
                ->orderByDesc('applied_at')
                ->get()
            : collect();

        $stages = $campaign
            ? $campaign->stages()->orderBy('position')->get(['id', 'name', 'position', 'is_final'])
                ->map(fn (LiveHostRecruitmentStage $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'position' => (int) $s->position,
                    'is_final' => (bool) $s->is_final,
                ])
            : collect();

        return Inertia::render('recruitment/applicants/Index', [
            'campaign' => $campaign ? [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'status' => $campaign->status,
            ] : null,
            'stages' => $stages->values(),
            'applicants' => $applicants->map(fn (LiveHostApplicant $a) => [
                'id' => $a->id,
                'applicant_number' => $a->applicant_number,
                'full_name' => $a->full_name,
                'email' => $a->email,
                'platforms' => $a->platforms ?? [],
                'rating' => $a->rating,
                'current_stage_id' => $a->current_stage_id,
                'status' => $a->status,
                'applied_at' => $a->applied_at?->toIso8601String(),
                'applied_at_human' => $a->applied_at?->diffForHumans(),
            ])->values(),
            'campaigns' => LiveHostRecruitmentCampaign::orderByDesc('created_at')
                ->get(['id', 'title', 'status'])
                ->map(fn (LiveHostRecruitmentCampaign $c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'status' => $c->status,
                ])
                ->values(),
            'filters' => [
                'campaign' => $campaign?->id,
                'status' => $statusTab,
            ],
        ]);
    }

    public function show(LiveHostApplicant $applicant): Response
    {
        $applicant->load([
            'campaign.stages' => fn ($q) => $q->orderBy('position'),
            'currentStage',
            'history' => fn ($q) => $q->latest(),
            'history.fromStage',
            'history.toStage',
            'history.changedByUser',
        ]);

        return Inertia::render('recruitment/applicants/Show', [
            'applicant' => [
                'id' => $applicant->id,
                'applicant_number' => $applicant->applicant_number,
                'full_name' => $applicant->full_name,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
                'ic_number' => $applicant->ic_number,
                'location' => $applicant->location,
                'platforms' => $applicant->platforms ?? [],
                'experience_summary' => $applicant->experience_summary,
                'motivation' => $applicant->motivation,
                'resume_path' => $applicant->resume_path,
                'source' => $applicant->source,
                'status' => $applicant->status,
                'rating' => $applicant->rating,
                'notes' => $applicant->notes,
                'applied_at' => $applicant->applied_at?->toIso8601String(),
                'applied_at_human' => $applicant->applied_at?->diffForHumans(),
                'hired_at' => $applicant->hired_at?->toIso8601String(),
                'hired_user_id' => $applicant->hired_user_id,
                'current_stage_id' => $applicant->current_stage_id,
                'current_stage' => $applicant->currentStage ? [
                    'id' => $applicant->currentStage->id,
                    'name' => $applicant->currentStage->name,
                    'position' => (int) $applicant->currentStage->position,
                    'is_final' => (bool) $applicant->currentStage->is_final,
                ] : null,
                'campaign' => [
                    'id' => $applicant->campaign->id,
                    'title' => $applicant->campaign->title,
                    'slug' => $applicant->campaign->slug,
                    'status' => $applicant->campaign->status,
                ],
            ],
            'stages' => $applicant->campaign->stages->map(fn (LiveHostRecruitmentStage $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'position' => (int) $s->position,
                'is_final' => (bool) $s->is_final,
            ])->values(),
            'history' => $applicant->history->map(fn ($h) => [
                'id' => $h->id,
                'action' => $h->action,
                'notes' => $h->notes,
                'from_stage' => $h->fromStage ? ['id' => $h->fromStage->id, 'name' => $h->fromStage->name] : null,
                'to_stage' => $h->toStage ? ['id' => $h->toStage->id, 'name' => $h->toStage->name] : null,
                'changed_by' => $h->changedByUser ? ['id' => $h->changedByUser->id, 'name' => $h->changedByUser->name] : null,
                'created_at' => $h->created_at?->toIso8601String(),
                'created_at_human' => $h->created_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function moveStage(Request $request, LiveHostApplicant $applicant): RedirectResponse
    {
        abort_if($applicant->status !== 'active', HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Applicant is not active.');

        $data = $request->validate([
            'to_stage_id' => ['required', 'integer', 'exists:live_host_recruitment_stages,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $toStage = LiveHostRecruitmentStage::findOrFail($data['to_stage_id']);
        abort_unless(
            $toStage->campaign_id === $applicant->campaign_id,
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Stage does not belong to this campaign.'
        );

        $fromStageId = $applicant->current_stage_id;
        $applicant->loadMissing('currentStage');

        $action = 'advanced';
        if ($applicant->currentStage && $toStage->position < $applicant->currentStage->position) {
            $action = 'reverted';
        }

        DB::transaction(function () use ($applicant, $toStage, $fromStageId, $data, $action, $request) {
            $applicant->update(['current_stage_id' => $toStage->id]);
            $applicant->history()->create([
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $toStage->id,
                'action' => $action,
                'notes' => $data['notes'] ?? null,
                'changed_by' => $request->user()?->id,
            ]);
        });

        return back()->with('success', "Moved to stage \"{$toStage->name}\".");
    }

    public function reject(Request $request, LiveHostApplicant $applicant): RedirectResponse
    {
        abort_if($applicant->status !== 'active', HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Applicant is not active.');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($applicant, $data, $request) {
            $applicant->history()->create([
                'from_stage_id' => $applicant->current_stage_id,
                'to_stage_id' => null,
                'action' => 'rejected',
                'notes' => $data['notes'] ?? null,
                'changed_by' => $request->user()?->id,
            ]);
            $applicant->update(['status' => 'rejected']);
        });

        return back()->with('success', 'Applicant rejected.');
    }

    public function updateNotes(Request $request, LiveHostApplicant $applicant): HttpResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $applicant->update(['notes' => $data['notes'] ?? null]);

        return response()->noContent();
    }
}
