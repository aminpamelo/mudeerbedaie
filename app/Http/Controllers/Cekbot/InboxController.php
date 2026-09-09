<?php

namespace App\Http\Controllers\Cekbot;

use App\Events\Cekbot\CekbotMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\CekbotConversation;
use App\Models\CekbotConversationNote;
use App\Models\CekbotLabel;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(private WahaSessionManager $waha) {}

    public function index(Request $request): Response
    {
        $sessionId = $request->integer('session') ?: null;

        $conversations = CekbotConversation::query()
            ->with(['session:id,label,session_name,phone_number', 'handedOverBy:id,name', 'assignee:id,name'])
            ->active()
            ->when($sessionId, fn ($q) => $q->where('cekbot_session_id', $sessionId))
            ->orderByDesc('last_message_at')
            ->limit(200)
            ->get()
            ->map(fn (CekbotConversation $c) => $this->shapeConversation($c));

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'sessions' => CekbotSession::query()
                ->orderBy('label')
                ->get(['id', 'label', 'session_name', 'phone_number', 'status'])
                ->map(fn (CekbotSession $s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'phone_number' => $s->phone_number,
                    'is_working' => $s->isWorking(),
                ]),
            'filterSessionId' => $sessionId,
            'staff' => \App\Models\User::query()
                ->whereIn('role', ['admin', 'employee'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'availableLabels' => CekbotLabel::options(),
        ]);
    }

    public function messages(CekbotConversation $conversation): JsonResponse
    {
        $conversation->markAsRead();

        $messages = $conversation->messages()
            ->with('sentBy:id,name')
            ->orderBy('id')
            ->limit(300)
            ->get()
            ->map(fn (CekbotMessage $m) => $this->shapeMessage($m));

        return response()->json([
            'conversation' => $this->shapeConversation($conversation->load(['session:id,label,session_name,phone_number', 'handedOverBy:id,name', 'assignee:id,name'])),
            'messages' => $messages,
            'notes' => $conversation->notes()->with('author:id,name')->latest('id')->get()->map(fn (CekbotConversationNote $n) => [
                'id' => $n->id,
                'body' => $n->body,
                'author' => $n->author?->name,
                'created_ago' => $n->created_at?->diffForHumans(),
            ]),
        ]);
    }

    public function reply(Request $request, CekbotConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4096',
        ]);

        $conversation->loadMissing('session');
        $result = $this->waha->sendText(
            $conversation->session->session_name,
            $conversation->chat_id,
            $validated['message'],
        );

        $message = CekbotMessage::create([
            'cekbot_conversation_id' => $conversation->id,
            'cekbot_session_id' => $conversation->cekbot_session_id,
            'waha_message_id' => $result['message_id'] ?? null,
            'direction' => CekbotMessage::DIRECTION_OUT,
            'from_me' => true,
            'type' => 'text',
            'body' => $validated['message'],
            'ack' => $result['success'] ? 'sent' : 'failed',
            'sent_by_user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        // A manual reply hands the conversation over to the human (pauses the bot).
        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($validated['message'], 255),
            'handed_over_at' => $conversation->handed_over_at ?? now(),
            'handed_over_by' => $conversation->handed_over_by ?? $request->user()->id,
        ]);

        CekbotMessageReceived::dispatch($conversation->id, $conversation->cekbot_session_id, 'out');

        return response()->json([
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'message' => $this->shapeMessage($message->load('sentBy:id,name')),
        ], $result['success'] ? 200 : 422);
    }

    public function handover(Request $request, CekbotConversation $conversation): RedirectResponse
    {
        $conversation->update(['handed_over_at' => now(), 'handed_over_by' => $request->user()->id]);

        return back()->with('success', 'Anda mengambil alih perbualan ini. Bot dijeda.');
    }

    public function release(CekbotConversation $conversation): RedirectResponse
    {
        $conversation->update(['handed_over_at' => null, 'handed_over_by' => null]);

        return back()->with('success', 'Perbualan diserah semula kepada bot.');
    }

    public function archive(CekbotConversation $conversation): RedirectResponse
    {
        $conversation->update(['archived_at' => now()]);

        return back()->with('success', 'Perbualan diarkibkan.');
    }

    /**
     * Stream a message's media through the app (WAHA files aren't
     * browser-reachable and may need the API key).
     */
    public function media(CekbotMessage $message): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($message->media_url, 404);

        $media = $this->waha->fetchMediaBody($message->media_url);

        abort_unless($media, 404);

        return response($media['body'], 200)
            ->header('Content-Type', $media['mime'])
            ->header('Cache-Control', 'private, max-age=3600');
    }

    public function assign(Request $request, CekbotConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $conversation->update(['assigned_to' => $validated['assigned_to'] ?? null]);

        return back()->with('success', $validated['assigned_to'] ? 'Perbualan ditugaskan.' : 'Tugasan dibuang.');
    }

    public function setLabels(Request $request, CekbotConversation $conversation): RedirectResponse
    {
        $allowed = CekbotLabel::allowedKeys();
        $validated = $request->validate([
            'labels' => 'array',
            'labels.*' => ['string', Rule::in($allowed)],
        ]);

        $conversation->update(['labels' => array_values(array_unique($validated['labels'] ?? []))]);

        return back()->with('success', 'Label dikemas kini.');
    }

    public function addNote(Request $request, CekbotConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $conversation->notes()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Nota ditambah.');
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeConversation(CekbotConversation $c): array
    {
        return [
            'id' => $c->id,
            'chat_id' => $c->chat_id,
            'phone' => $c->phoneNumber(),
            'name' => $c->name,
            'is_group' => $c->is_group,
            'unread_count' => $c->unread_count,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'last_message_preview' => $c->last_message_preview,
            'handed_over' => $c->handed_over_at ? [
                'at' => $c->handed_over_at->toIso8601String(),
                'by' => $c->relationLoaded('handedOverBy') ? $c->handedOverBy?->name : null,
            ] : null,
            'assigned_to' => $c->assigned_to,
            'assignee' => $c->relationLoaded('assignee') ? $c->assignee?->name : null,
            'labels' => $c->labels ?? [],
            'session' => $c->relationLoaded('session') && $c->session ? [
                'id' => $c->session->id,
                'label' => $c->session->label,
                'phone' => $c->session->phone_number,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeMessage(CekbotMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'from_me' => $m->from_me,
            'type' => $m->type,
            'body' => $m->body,
            'media_url' => $m->media_url ? route('cekbot.inbox.media', $m->id) : null,
            'ack' => $m->ack,
            'sent_by' => $m->sentBy?->name,
            'sent_at' => ($m->sent_at ?? $m->created_at)?->toIso8601String(),
        ];
    }
}
