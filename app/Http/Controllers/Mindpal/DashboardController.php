<?php

namespace App\Http\Controllers\Mindpal;

use App\Http\Controllers\Controller;
use App\Models\MindpalChunk;
use App\Models\MindpalConversation;
use App\Models\MindpalDocument;
use App\Models\MindpalMessage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Overview', [
            'stats' => [
                'totalDocuments' => MindpalDocument::count(),
                'readyDocuments' => MindpalDocument::ready()->count(),
                'totalChunks' => MindpalChunk::count(),
                'totalConversations' => MindpalConversation::count(),
                'totalMessages' => MindpalMessage::count(),
            ],
            'recentDocuments' => MindpalDocument::query()
                ->with('uploader:id,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'status' => $d->status,
                    'total_pages' => $d->total_pages,
                    'total_chunks' => $d->total_chunks,
                    'uploader' => $d->uploader?->name,
                    'created_at' => $d->created_at->diffForHumans(),
                ]),
        ]);
    }
}
