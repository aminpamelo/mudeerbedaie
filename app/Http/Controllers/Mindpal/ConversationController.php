<?php

namespace App\Http\Controllers\Mindpal;

use App\Http\Controllers\Controller;
use App\Models\MindpalConversation;
use App\Services\MindpalChatService;
use Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Start a new admin-owned conversation to chat with the knowledge base.
     */
    public function store(Request $request): JsonResponse
    {
        $conversation = MindpalConversation::create([
            'user_id' => $request->user()->id,
            'title' => 'New Conversation',
        ]);

        return response()->json(['id' => $conversation->id]);
    }

    /**
     * Stream an answer for a message in the given conversation.
     */
    public function send(Request $request, MindpalConversation $conversation, MindpalChatService $chatService): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = $request->input('message');

        return response()->stream(function () use ($conversation, $message, $chatService) {
            $this->pumpStream($chatService->streamAsk($conversation, $message), $conversation);
        }, 200, $this->streamHeaders());
    }

    /**
     * Stream generator tokens as SSE, then emit the saved answer's sources.
     *
     * @param  Generator<int, string>  $generator
     */
    private function pumpStream(Generator $generator, MindpalConversation $conversation): void
    {
        foreach ($generator as $token) {
            echo 'data: '.json_encode(['token' => $token])."\n\n";
            $this->flushOutput();
        }

        $answer = $conversation->messages()->where('role', 'assistant')->latest('id')->first();

        if ($answer !== null && ! empty($answer->sources)) {
            echo 'data: '.json_encode(['sources' => $answer->sources])."\n\n";
            $this->flushOutput();
        }

        echo "data: [DONE]\n\n";
        $this->flushOutput();
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * @return array<string, string>
     */
    private function streamHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }
}
