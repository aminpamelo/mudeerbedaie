<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppGroupCollection;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WhatsAppGroupDirectoryController extends Controller
{
    public function show(WhatsAppGroupCollection $collection): View
    {
        if (! $collection->is_active) {
            throw new NotFoundHttpException;
        }

        $collection->load(['activeItems.class']);

        $groups = $collection->activeItems
            ->filter(fn ($item) => filled($item->effective_link))
            ->values();

        return view('wa-groups.show', [
            'collection' => $collection,
            'groups' => $groups,
        ]);
    }
}
