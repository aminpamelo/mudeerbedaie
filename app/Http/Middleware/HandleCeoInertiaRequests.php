<?php

namespace App\Http\Middleware;

/**
 * Inertia middleware for the CEO Overview bundle.
 *
 * The base `HandleInertiaRequests` middleware is appended to every `web`
 * request (its `$rootView` targets the Live Host Desk). This subclass runs as
 * route-level middleware on the `/ceo/*` group so its `$rootView` overrides the
 * PIC root view for CEO requests only. Shared props (auth, brand, flash) are
 * inherited unchanged from the parent — the CEO bundle needs nothing extra.
 */
class HandleCeoInertiaRequests extends HandleInertiaRequests
{
    /**
     * The root template that's loaded on the first page visit for the CEO app.
     *
     * @var string
     */
    protected $rootView = 'ceo.app';
}
