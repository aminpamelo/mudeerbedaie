<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

/**
 * Inertia middleware for the standalone Forms app (/forms). Overrides the
 * root view so the Forms React bundle is loaded, and exposes a lightweight
 * `isAdmin` flag so the frontend can reveal the admin oversight surfaces
 * without pattern-matching on role strings.
 */
class HandleFormsInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'forms.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'isFormsAdmin' => fn (): bool => (bool) $request->user()?->isAdmin(),
        ];
    }
}
