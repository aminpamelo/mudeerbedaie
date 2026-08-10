<?php

namespace App\Http\Middleware;

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
        ];
    }
}
