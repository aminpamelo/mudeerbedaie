<?php

namespace App\Http\Middleware;

use App\Models\BlogComment;
use Illuminate\Http\Request;

/**
 * Inertia middleware for the Blog & SEO workspace.
 *
 * The base `HandleInertiaRequests` middleware is appended to every `web`
 * request (its `$rootView` targets the Live Host Desk). This subclass runs as
 * route-level middleware on the `/blog-seo/*` group so its `$rootView`
 * overrides that for workspace requests only, and shares the pending-comment
 * count that drives the sidebar moderation badge.
 */
class HandleBlogSeoInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'blogseo.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'pendingComments' => fn () => BlogComment::query()->pending()->count(),
        ];
    }
}
