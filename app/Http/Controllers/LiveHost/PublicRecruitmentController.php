<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\Recruitment\ApplyRequest;
use App\Mail\LiveHost\Recruitment\ApplicationReceivedMail;
use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PublicRecruitmentController extends Controller
{
    public function show(string $slug): View|Response
    {
        $campaign = LiveHostRecruitmentCampaign::where('slug', $slug)->firstOrFail();

        if (! $campaign->isAcceptingApplications()) {
            return response()->view('recruitment.closed', ['campaign' => $campaign], Response::HTTP_GONE);
        }

        return view('recruitment.show', ['campaign' => $campaign]);
    }

    public function apply(ApplyRequest $request, string $slug): RedirectResponse
    {
        $campaign = LiveHostRecruitmentCampaign::with('stages')->where('slug', $slug)->firstOrFail();

        abort_unless($campaign->isAcceptingApplications(), Response::HTTP_GONE);

        $validated = $request->validated();
        $schema = $campaign->form_schema ?? [];

        $emailFieldId = $this->emailFieldIdFromSchema($schema);

        abort_if($emailFieldId === null, 422, 'Campaign schema is missing an email-role field.');

        $emailValue = $validated[$emailFieldId] ?? null;

        abort_if($emailValue === null, 422, 'Campaign schema is missing an email-role field.');

        if (LiveHostApplicant::query()
            ->where('campaign_id', $campaign->id)
            ->where('email', $emailValue)
            ->exists()
        ) {
            return back()->withInput()->withErrors([
                $emailFieldId => 'You have already applied to this campaign with this email.',
            ]);
        }

        // Handle file uploads: store to disk, replace request value with the path
        $uploadedPaths = [];
        foreach ($campaign->getAllFields() as $field) {
            if (($field['type'] ?? null) === 'file' && $request->hasFile($field['id'])) {
                $path = $request->file($field['id'])->store('recruitment/resumes', 'local');
                $uploadedPaths[$field['id']] = $path;
                $validated[$field['id']] = $path;
            }
        }

        try {
            $applicant = DB::transaction(function () use ($validated, $campaign, $schema) {
                $firstStage = $campaign->stages->sortBy('position')->first();

                $applicant = LiveHostApplicant::create([
                    'campaign_id' => $campaign->id,
                    'applicant_number' => LiveHostApplicant::generateApplicantNumber(),
                    'form_data' => $validated,
                    'form_schema_snapshot' => $schema,
                    'current_stage_id' => $firstStage?->id,
                    'status' => 'active',
                    'applied_at' => now(),
                ]);

                $applicant->history()->create([
                    'to_stage_id' => $firstStage?->id,
                    'action' => 'applied',
                ]);

                app(\App\Services\Recruitment\ApplicantStageTransition::class)->enterFirstStage($applicant);

                return $applicant;
            });
        } catch (QueryException $e) {
            foreach ($uploadedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            if ($e->getCode() === '23000') {
                return back()->withInput()->withErrors([
                    $emailFieldId => 'You have already applied to this campaign with this email.',
                ]);
            }

            throw $e;
        }

        Mail::to((string) $applicant->email)->queue(new ApplicationReceivedMail($applicant));

        return redirect()
            ->route('recruitment.thank-you', $slug)
            ->with('applicant_number', $applicant->applicant_number)
            ->with('applicant_name', $applicant->valueByRole('name') ?? '')
            ->with('applicant_email', $applicant->email);
    }

    public function thankYou(string $slug): View
    {
        $campaign = LiveHostRecruitmentCampaign::where('slug', $slug)->firstOrFail();

        return view('recruitment.thank-you', [
            'campaign' => $campaign,
            'applicantNumber' => session('applicant_number'),
            'applicantName' => session('applicant_name'),
            'applicantEmail' => session('applicant_email'),
        ]);
    }

    private function emailFieldIdFromSchema(array $schema): ?string
    {
        foreach (($schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                if (($field['role'] ?? null) === 'email') {
                    return $field['id'];
                }
            }
        }

        return null;
    }
}
