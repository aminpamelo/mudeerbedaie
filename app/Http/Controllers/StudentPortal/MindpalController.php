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

        $message = $request->input('message');

        return response()->stream(function () use ($conversation, $message, $chatService) {
            $this->pumpStream($chatService->streamAsk($conversation, $message), $conversation);
        }, 200, $this->streamHeaders());
    }

    /**
     * Re-answer the last question in the conversation, replacing the previous answer.
     */
    public function regenerate(Request $request, MindpalConversation $conversation, MindpalChatService $chatService): StreamedResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'You do not have permission to modify this conversation.');
        }

        $lastUser = $conversation->messages()->where('role', 'user')->latest('id')->first();

        abort_if($lastUser === null, 422, 'There is no question to regenerate.');

        $question = $lastUser->content;

        // Drop the previous question and its answer, then ask again from clean history.
        $conversation->messages()->where('id', '>=', $lastUser->id)->delete();

        return response()->stream(function () use ($conversation, $question, $chatService) {
            $this->pumpStream($chatService->streamAsk($conversation, $question), $conversation);
        }, 200, $this->streamHeaders());
    }

    /**
     * Stream generator tokens as SSE, then emit the saved answer's sources.
     *
     * @param  \Generator<int, string>  $generator
     */
    private function pumpStream(\Generator $generator, MindpalConversation $conversation): void
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

    public function destroy(Request $request, MindpalConversation $conversation): RedirectResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'You do not have permission to delete this conversation.');
        }

        $conversation->delete();

        return redirect()->route('student.mindpal');
    }
}
