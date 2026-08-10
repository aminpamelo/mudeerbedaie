<?php

namespace App\Http\Middleware;

use App\Services\TmsPermissionService;
use Illuminate\Http\Request;

class HandleWorkspaceInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'workspace.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'tmsPermissions' => fn () => $request->user()
                ? app(TmsPermissionService::class)->resolve($request->user())
                : [],
        ];
    }
}
