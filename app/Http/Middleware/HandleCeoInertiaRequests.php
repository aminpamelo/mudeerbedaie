<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inertia middleware for the CEO Overview bundle.
 *
 * The base `HandleInertiaRequests` middleware is appended to every `web`
 * request (its `$rootView` targets the Live Host Desk). This subclass runs as
 * route-level middleware on the `/ceo/*` group so its `$rootView` overrides the
 * PIC root view for CEO requests only.
 *
 * It also pins the CEO app to its own locale (default Malay), independent of the
 * user's global account locale, so the executive dashboard reads in Malay first
 * while the rest of the app stays in whatever language the user set. The chosen
 * locale lives in the session and is shared to React alongside the UI string
 * dictionary so frontend-only labels translate without an API round-trip.
 */
class HandleCeoInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'ceo.app';

    private const SUPPORTED = ['ms', 'en'];

    private const DEFAULT = 'ms';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->ceoLocale($request));

        return parent::handle($request, $next);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'ceoLocale' => app()->getLocale(),
            'ceoLocales' => [
                ['key' => 'ms', 'label' => 'BM'],
                ['key' => 'en', 'label' => 'EN'],
            ],
            'i18n' => fn () => array_merge(__('ceo.ui'), [
                'dept_livehost' => __('ceo.departments.livehost'),
                'dept_education' => __('ceo.departments.education'),
                'dept_ecommerce' => __('ceo.departments.ecommerce'),
                'dept_hr' => __('ceo.departments.hr'),
            ]),
        ];
    }

    private function ceoLocale(Request $request): string
    {
        $locale = $request->session()->get('ceo_locale', self::DEFAULT);

        return in_array($locale, self::SUPPORTED, true) ? $locale : self::DEFAULT;
    }
}
