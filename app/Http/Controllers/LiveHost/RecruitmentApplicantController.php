<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\Recruitment\UpdateApplicantCurrentStageRequest;
use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use App\Models\User;
use App\Services\Recruitment\ApplicantStageTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentApplicantController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

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
                ->with(['currentStageRow.assignee'])
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

        $counts = $campaign
            ? [
                'active' => LiveHostApplicant::where('campaign_id', $campaign->id)->where('status', 'active')->count(),
                'rejected' => LiveHostApplicant::where('campaign_id', $campaign->id)->where('status', 'rejected')->count(),
                'hired' => LiveHostApplicant::where('campaign_id', $campaign->id)->where('status', 'hired')->count(),
            ]
            : ['active' => 0, 'rejected' => 0, 'hired' => 0];

        return Inertia::render('recruitment/applicants/Index', [
            'campaign' => $campaign ? [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'status' => $campaign->status,
                'description' => $campaign->description,
                'public_url' => $campaign->status === 'open'
                    ? route('recruitment.show', $campaign->slug)
                    : null,
                'opens_at' => $campaign->opens_at?->toIso8601String(),
                'closes_at' => $campaign->closes_at?->toIso8601String(),
            ] : null,
            'counts' => $counts,
            'stages' => $stages->values(),
            'applicants' => $applicants->map(function (LiveHostApplicant $a) {
                $row = $a->currentStageRow;

                return [
                    'id' => $a->id,
                    'applicant_number' => $a->applicant_number,
                    'full_name' => $a->name,
                    'email' => $a->email,
                    'platforms' => $a->valueByRole('platforms') ?? [],
                    'rating' => $a->rating,
                    'current_stage_id' => $a->current_stage_id,
                    'status' => $a->status,
                    'applied_at' => $a->applied_at?->toIso8601String(),
                    'applied_at_human' => $a->applied_at?->diffForHumans(),
                    'assignment' => $row ? [
                        'assignee' => $row->assignee ? [
                            'id' => $row->assignee->id,
                            'name' => $row->assignee->name,
                            'initials' => self::initials($row->assignee->name),
                        ] : null,
                        'due_at' => $row->due_at?->toIso8601String(),
                        'is_overdue' => $row->is_overdue,
                        'stage_notes' => $row->stage_notes,
                    ] : null,
                ];
            })->values(),
            'campaigns' => LiveHostRecruitmentCampaign::orderByDesc('created_at')
                ->get(['id', 'title', 'status'])
                ->map(fn (LiveHostRecruitmentCampaign $c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'status' => $c->status,
                ])
                ->values(),
            'assignableUsers' => User::query()
                ->whereIn('role', ['admin', 'admin_livehost'])
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'initials' => self::initials($u->name),
                ])
                ->values(),
            'filters' => [
                'campaign' => $campaign?->id,
                'status' => $statusTab,
            ],
        ]);
    }

    private static function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    public function show(Request $request, LiveHostApplicant $applicant): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

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
                'full_name' => $applicant->name,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
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
                'form_data' => $applicant->form_data,
                'form_schema_snapshot' => $applicant->form_schema_snapshot,
                'resume_path' => $applicant->resume_path,
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
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
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
            app(ApplicantStageTransition::class)->transition($applicant, $toStage);
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
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
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
            app(ApplicantStageTransition::class)->closeOpenRow($applicant);
            $applicant->update(['status' => 'rejected']);
        });

        return back()->with('success', 'Applicant rejected.');
    }

    public function updateNotes(Request $request, LiveHostApplicant $applicant): HttpResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $applicant->update(['notes' => $data['notes'] ?? null]);

        return response()->noContent();
    }

    public function updateCurrentStage(
        UpdateApplicantCurrentStageRequest $request,
        LiveHostApplicant $applicant,
    ): HttpResponse {
        $data = $request->validated();

        $affected = LiveHostApplicantStage::query()
            ->where('applicant_id', $applicant->id)
            ->whereNull('exited_at')
            ->update([
                'assignee_id' => $data['assignee_id'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'stage_notes' => $data['stage_notes'] ?? null,
                'updated_at' => now(),
            ]);

        abort_if($affected === 0, HttpResponse::HTTP_CONFLICT, 'No open stage row.');

        return response()->noContent();
    }

    public function hire(Request $request, LiveHostApplicant $applicant): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_if(
            $applicant->status !== 'active',
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Applicant is not active.'
        );

        $applicant->loadMissing('currentStage');
        abort_unless(
            optional($applicant->currentStage)->is_final,
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Applicant is not at the final stage.'
        );

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $user = DB::transaction(function () use ($applicant, $data, $request): User {
            $user = User::create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make(Str::random(40)),
                'role' => 'live_host',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $applicant->history()->create([
                'from_stage_id' => $applicant->current_stage_id,
                'to_stage_id' => null,
                'action' => 'hired',
                'notes' => "Hired as user #{$user->id}",
                'changed_by' => $request->user()?->id,
            ]);

            app(ApplicantStageTransition::class)->closeOpenRow($applicant);

            $applicant->update([
                'status' => 'hired',
                'hired_at' => now(),
                'hired_user_id' => $user->id,
            ]);

            return $user;
        });

        return back()
            ->with('success', "Hired {$user->name} as a live host.")
            ->with('hired_user_id', $user->id);
    }

    public function passwordResetLink(Request $request, LiveHostApplicant $applicant): JsonResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_unless(
            $applicant->status === 'hired' && $applicant->hired_user_id,
            HttpResponse::HTTP_NOT_FOUND
        );

        $user = $applicant->hiredUser;
        abort_unless($user, HttpResponse::HTTP_NOT_FOUND);

        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

        return response()->json(['url' => $url]);
    }
}
