<?php

namespace App\Http\Controllers\Mindpal;

use App\Http\Controllers\Controller;
use App\Models\MindpalConversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search', ''),
        ];

        $conversations = MindpalConversation::query()
            ->with('user:id,name,email')
            ->withCount('messages')
            ->when($filters['search'], fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($conv) => [
                'id' => $conv->id,
                'title' => $conv->title ?? 'Untitled',
                'user' => $conv->user ? ['name' => $conv->user->name, 'email' => $conv->user->email] : null,
                'messages_count' => $conv->messages_count,
                'updated_at' => $conv->updated_at->diffForHumans(),
                'created_at' => $conv->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Conversations', [
            'conversations' => $conversations,
            'filters' => $filters,
            'totalConversations' => MindpalConversation::count(),
        ]);
    }

    public function show(MindpalConversation $conversation): Response
    {
        $conversation->load('user:id,name,email');
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'sources', 'tokens_used', 'created_at']);

        return Inertia::render('ConversationDetail', [
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title ?? 'Untitled',
                'user' => $conversation->user ? ['name' => $conversation->user->name, 'email' => $conversation->user->email] : null,
                'created_at' => $conversation->created_at->toDateTimeString(),
            ],
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'sources' => $m->sources,
                'tokens_used' => $m->tokens_used,
                'created_at' => $m->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function destroy(MindpalConversation $conversation): RedirectResponse
    {
        $conversation->delete();

        return redirect()->route('mindpal.conversations.index')->with('success', 'Conversation deleted.');
    }
}
