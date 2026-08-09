<?php

namespace App\Http\Middleware;

use App\Models\MindpalDocument;
use Illuminate\Http\Request;

/**
 * Inertia middleware for the MindPal Knowledge Base workspace.
 *
 * Overrides the root view to `mindpal.app` and shares document
 * counts so the sidebar can display them without each controller passing them.
 */
class HandleMindpalInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'mindpal.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'totalDocuments' => fn () => MindpalDocument::query()->count(),
            'readyDocuments' => fn () => MindpalDocument::query()->ready()->count(),
        ];
    }
}
