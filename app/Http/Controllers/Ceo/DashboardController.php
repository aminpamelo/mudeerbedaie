<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Services\Ceo\CeoDashboardService;
use App\Services\Ceo\CeoPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, CeoDashboardService $service): Response
    {
        $period = CeoPeriod::fromRequest($request);

        return Inertia::render('Dashboard', $service->build($period));
    }

    /**
     * Switch the CEO dashboard language (session-scoped, independent of the
     * user's global account locale). Redirects back so the Inertia visit reloads
     * the page in the chosen language.
     */
    public function setLocale(Request $request): RedirectResponse
    {
        $locale = $request->input('locale');

        if (in_array($locale, ['en', 'ms'], true)) {
            $request->session()->put('ceo_locale', $locale);
        }

        return back();
    }
}
