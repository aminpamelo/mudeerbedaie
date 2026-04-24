<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostRecruitmentCampaign;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function apply(Request $request, string $slug): RedirectResponse
    {
        // filled in task 2.3
        return redirect()->route('recruitment.thank-you', $slug);
    }

    public function thankYou(string $slug): View
    {
        $campaign = LiveHostRecruitmentCampaign::where('slug', $slug)->firstOrFail();

        return view('recruitment.thank-you', ['campaign' => $campaign]);
    }
}
