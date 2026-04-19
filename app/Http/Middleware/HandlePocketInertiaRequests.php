<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

/**
 * Inertia middleware for the Live Host Pocket bundle.
 *
 * The base `HandleInertiaRequests` middleware is appended to every `web`
 * request for the Live Host Desk (PIC dashboard). This subclass runs as a
 * route-level middleware on the `/live-host/*` group so its `$rootView`
 * overrides the PIC root view for Pocket requests only, and so it can
 * add Pocket-specific shared props without leaking them to the admin UI.
 */
class HandlePocketInertiaRequests extends HandleInertiaRequests
{
    /**
     * The root template that's loaded on the first page visit for Pocket.
     *
     * @var string
     */
    protected $rootView = 'livehost-pocket.app';

    /**
     * Shared props for every Pocket page. The PIC navCounts/branding
     * helpers from the parent are carried through unchanged so either
     * bundle can use them; Pocket adds its own feature flag bag.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'features' => [
                'allowance_enabled' => (bool) config('livehost.allowance_enabled'),
            ],
        ];
    }
}
