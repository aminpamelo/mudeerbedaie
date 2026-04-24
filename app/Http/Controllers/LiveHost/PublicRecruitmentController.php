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

        if (LiveHostApplicant::query()
            ->where('campaign_id', $campaign->id)
            ->where('email', $request->string('email'))
            ->exists()
        ) {
            return back()->withInput()->withErrors([
                'email' => 'You have already applied to this campaign with this email.',
            ]);
        }

        $resumePath = $request->file('resume')?->store('recruitment/resumes', 'local');

        try {
            $applicant = DB::transaction(function () use ($request, $campaign, $resumePath) {
                $firstStage = $campaign->stages->sortBy('position')->first();

                $applicant = LiveHostApplicant::create([
                    'campaign_id' => $campaign->id,
                    'applicant_number' => LiveHostApplicant::generateApplicantNumber(),
                    'full_name' => $request->string('full_name'),
                    'email' => $request->string('email'),
                    'phone' => $request->string('phone'),
                    'ic_number' => $request->input('ic_number'),
                    'location' => $request->input('location'),
                    'platforms' => $request->input('platforms'),
                    'experience_summary' => $request->input('experience_summary'),
                    'motivation' => $request->input('motivation'),
                    'resume_path' => $resumePath,
                    'current_stage_id' => $firstStage?->id,
                    'status' => 'active',
                    'applied_at' => now(),
                ]);

                $applicant->history()->create([
                    'to_stage_id' => $firstStage?->id,
                    'action' => 'applied',
                ]);

                return $applicant;
            });
        } catch (QueryException $e) {
            if ($resumePath) {
                Storage::disk('local')->delete($resumePath);
            }

            if ($e->getCode() === '23000') {
                return back()->withInput()->withErrors([
                    'email' => 'You have already applied to this campaign with this email.',
                ]);
            }

            throw $e;
        }

        Mail::to((string) $applicant->email)->queue(new ApplicationReceivedMail($applicant));

        return redirect()->route('recruitment.thank-you', $slug);
    }

    public function thankYou(string $slug): View
    {
        $campaign = LiveHostRecruitmentCampaign::where('slug', $slug)->firstOrFail();

        return view('recruitment.thank-you', ['campaign' => $campaign]);
    }
}
