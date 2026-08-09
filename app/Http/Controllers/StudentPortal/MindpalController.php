<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\MindpalConversation;
use App\Models\MindpalDocument;
use App\Services\MindpalChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MindpalController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = MindpalConversation::query()
            ->forUser($request->user()->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MindpalConversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at->diffForHumans(),
            ]);

        $documentsCount = MindpalDocument::ready()->count();

        return Inertia::render('Mindpal', [
            'conversations' => $conversations,
            'documentsCount' => $documentsCount,
        ]);
    }

    public function show(Request $request, MindpalConversation $conversation): Response
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'You do not have permission to view this conversation.');
        }

        $conversations = MindpalConversation::query()
            ->forUser($request->user()->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MindpalConversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at->diffForHumans(),
            ]);

        $documentsCount = MindpalDocument::ready()->count();

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'sources' => $m->sources,
                'created_at' => $m->created_at->diffForHumans(),
            ]);

        return Inertia::render('Mindpal', [
            'conversations' => $conversations,
            'documentsCount' => $documentsCount,
            'activeConversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $messages,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $conversation = MindpalConversation::create([
            'user_id' => $request->user()->id,
            'title' => 'New Conversation',
        ]);

        return response()->json(['id' => $conversation->id]);
    }

    public function send(Request $request, MindpalConversation $conversation, MindpalChatService $chatService): StreamedResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'You do not have permission to send messages to this conversation.');
        }

        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        return response()->stream(function () use ($conversation, $request, $chatService) {
            $generator = $chatService->streamAsk($conversation, $request->input('message'));

            foreach ($generator as $token) {
                echo 'data: '.json_encode(['token' => $token])."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            echo "data: [DONE]\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function destroy(Request $request, MindpalConversation $conversation): RedirectResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'You do not have permission to delete this conversation.');
        }

        $conversation->delete();

        return redirect()->route('student.mindpal');
    }
}
