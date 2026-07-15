<?php

namespace App\Http\Middleware;

use App\Models\Funnel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a Fighter to only the funnels they own.
 *
 * The funnel-builder API endpoints resolve a funnel by its `{uuid}` /
 * `{funnelUuid}` route parameter with no ownership check — fine for admin/
 * employee (who manage every funnel), but a Fighter must never reach another
 * fighter's funnel by guessing a UUID. This middleware enforces ownership for
 * the `fighter` role only; every other role passes through untouched, and
 * routes without a funnel parameter (index, category, media, search) are a
 * no-op.
 */
class EnsureFunnelOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only Fighters are ownership-restricted; all other roles keep their
        // existing (broad) access so admin/employee funnel management is intact.
        if (! $user || ! $user->isFighter()) {
            return $next($request);
        }

        $uuid = $request->route('uuid') ?? $request->route('funnelUuid');

        if ($uuid) {
            $funnel = Funnel::query()->where('uuid', $uuid)->first();

            if (! $funnel) {
                abort(404);
            }

            if ((int) $funnel->user_id !== (int) $user->id) {
                abort(403, 'You do not have access to this funnel.');
            }
        }

        return $next($request);
    }
}
