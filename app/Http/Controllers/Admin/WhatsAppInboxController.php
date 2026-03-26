<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\TemplateService;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WhatsAppInboxController extends Controller
{
    /**
     * List conversations with search and status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WhatsAppConversation::query()
            ->with('student.user');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $conversations = $query->orderByDesc('last_message_at')
            ->paginate(50);

        $conversations->getCollection()->transform(function (WhatsAppConversation $conversation) {
            return [
                'id' => $conversation->id,
                'phone_number' => $conversation->phone_number,
                'contact_name' => $conversation->contact_name,
                'student_name' => $conversation->student?->user?->name,
                'student_id' => $conversation->student_id,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'last_message_preview' => $conversation->last_message_preview,
                'unread_count' => $conversation->unread_count,
                'is_service_window_open' => $conversation->isServiceWindowOpen(),
                'service_window_expires_at' => $conversation->service_window_expires_at?->toIso8601String(),
                'status' => $conversation->status,
            ];
        });

        return response()->json($conversations);
    }

    /**
     * Show messages for a conversation and mark as read.
     */
    public function show(WhatsAppConversation $conversation): JsonResponse
    {
        $conversation->markAsRead();

        $messages = $conversation->messages()
            ->with('sentBy')
            ->orderByDesc('created_at')
            ->paginate(100);

        $messages->getCollection()->transform(function (WhatsAppMessage $message) {
            return [
                'id' => $message->id,
                'direction' => $message->direction,
                'type' => $message->type,
                'body' => $message->body,
                'media_url' => $message->media_url,
                'media_filename' => $message->media_filename,
                'template_name' => $message->template_name,
                'status' => $message->status,
                'sent_by' => $message->sentBy?->name,
                'created_at' => $message->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'phone_number' => $conversation->phone_number,
                'contact_name' => $conversation->contact_name,
                'student_name' => $conversation->student?->user?->name,
                'student_id' => $conversation->student_id,
                'is_service_window_open' => $conversation->isServiceWindowOpen(),
                'service_window_expires_at' => $conversation->service_window_expires_at?->toIso8601String(),
                'status' => $conversation->status,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Reply to a conversation with a text message.
     */
    public function reply(Request $request, WhatsAppConversation $conversation, WhatsAppManager $manager): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:4096',
        ]);

        $result = $manager->provider()->send($conversation->phone_number, $request->input('message'));

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $request->input('message'),
            'wamid' => $result['message_id'] ?? null,
            'status' => $result['success'] ? 'sent' : 'failed',
            'status_updated_at' => now(),
            'sent_by_user_id' => $request->user()->id,
            'error_message' => $result['error'] ?? null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($request->input('message'), 255),
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'type' => $message->type,
                'body' => $message->body,
                'status' => $message->status,
                'sent_by' => $request->user()->name,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Send a template message to a conversation.
     */
    public function sendTemplate(Request $request, WhatsAppConversation $conversation, WhatsAppManager $manager): JsonResponse
    {
        $request->validate([
            'template_name' => 'required|string',
            'language' => 'required|string',
            'components' => 'nullable|array',
        ]);

        $result = $manager->provider()->sendTemplate(
            $conversation->phone_number,
            $request->input('template_name'),
            $request->input('language'),
            $request->input('components', []),
        );

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'template',
            'template_name' => $request->input('template_name'),
            'body' => "Template: {$request->input('template_name')}",
            'wamid' => $result['message_id'] ?? null,
            'status' => $result['success'] ? 'sent' : 'failed',
            'status_updated_at' => now(),
            'sent_by_user_id' => $request->user()->id,
            'error_message' => $result['error'] ?? null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => "Template: {$request->input('template_name')}",
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'type' => $message->type,
                'template_name' => $message->template_name,
                'body' => $message->body,
                'status' => $message->status,
                'sent_by' => $request->user()->name,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Archive a conversation.
     */
    public function archive(WhatsAppConversation $conversation): JsonResponse
    {
        $conversation->update(['status' => 'archived']);

        return response()->json(['success' => true]);
    }

    /**
     * Get approved templates.
     */
    public function templates(): JsonResponse
    {
        $templates = WhatsAppTemplate::approved()->get();

        return response()->json(['data' => $templates]);
    }

    /**
     * Sync templates from Meta API.
     */
    public function syncTemplates(TemplateService $templateService): JsonResponse
    {
        try {
            $count = $templateService->syncFromMeta();

            return response()->json([
                'success' => true,
                'count' => $count,
                'message' => "Berjaya menyegerakkan {$count} templat.",
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
