<?php

namespace App\Http\Controllers\Mindpal;

use App\Http\Controllers\Controller;
use App\Models\MindpalConversation;
use App\Models\MindpalDocument;
use App\Models\MindpalMessage;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(): Response
    {
        // Messages per day (last 30 days)
        $messagesPerDay = MindpalMessage::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => $r->count]);

        // Top documents by chunk count
        $topDocuments = MindpalDocument::query()
            ->withCount('chunks')
            ->ready()
            ->orderByDesc('chunks_count')
            ->limit(10)
            ->get(['id', 'title', 'total_pages', 'total_chunks']);

        // Active users (most conversations)
        $activeUsers = MindpalConversation::query()
            ->with('user:id,name,email')
            ->select('user_id')
            ->selectRaw('COUNT(*) as conversations_count')
            ->groupBy('user_id')
            ->orderByDesc('conversations_count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'user' => $r->user ? ['name' => $r->user->name, 'email' => $r->user->email] : null,
                'conversations_count' => $r->conversations_count,
            ]);

        // Total tokens used
        $totalTokens = MindpalMessage::sum('tokens_used');

        return Inertia::render('Analytics', [
            'messagesPerDay' => $messagesPerDay,
            'topDocuments' => $topDocuments,
            'activeUsers' => $activeUsers,
            'totalTokens' => $totalTokens,
            'stats' => [
                'totalMessages' => MindpalMessage::count(),
                'totalConversations' => MindpalConversation::count(),
                'avgMessagesPerConversation' => MindpalConversation::count() > 0
                    ? round(MindpalMessage::count() / MindpalConversation::count(), 1)
                    : 0,
                'messagesThisWeek' => MindpalMessage::where('created_at', '>=', now()->subWeek())->count(),
            ],
        ]);
    }
}
