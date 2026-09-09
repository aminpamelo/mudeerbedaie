<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Jobs\SendCekbotBroadcastJob;
use App\Models\CekbotBroadcast;
use App\Models\CekbotConversation;
use App\Models\CekbotLabel;
use App\Models\CekbotSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function index(): Response
    {
        $broadcasts = CekbotBroadcast::query()
            ->with('session:id,label')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (CekbotBroadcast $b) => $this->shape($b));

        return Inertia::render('Broadcast/Index', [
            'broadcasts' => $broadcasts,
            'sessions' => CekbotSession::query()->orderBy('label')->get(['id', 'label', 'phone_number'])
                ->map(fn ($s) => ['id' => $s->id, 'label' => $s->label, 'phone_number' => $s->phone_number]),
            'availableLabels' => CekbotLabel::options(),
        ]);
    }

    public function recipientsCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cekbot_session_id' => 'required|exists:cekbot_sessions,id',
            'audience_type' => 'required|in:all,label',
            'audience_value' => 'nullable|string',
            'include_groups' => 'boolean',
        ]);

        $count = $this->recipientQuery(
            (int) $validated['cekbot_session_id'],
            $validated['audience_type'],
            $validated['audience_value'] ?? null,
            (bool) ($validated['include_groups'] ?? false),
        )->count();

        return response()->json(['count' => $count]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cekbot_session_id' => 'required|exists:cekbot_sessions,id',
            'name' => 'required|string|max:255',
            'message' => 'required|string|max:4096',
            'audience_type' => 'required|in:all,label',
            'audience_value' => 'nullable|required_if:audience_type,label|string',
            'include_groups' => 'boolean',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $scheduled = ! empty($validated['scheduled_at']);

        $broadcast = CekbotBroadcast::create([
            'cekbot_session_id' => $validated['cekbot_session_id'],
            'name' => $validated['name'],
            'message' => $validated['message'],
            'audience_type' => $validated['audience_type'],
            'audience_value' => $validated['audience_value'] ?? null,
            'include_groups' => (bool) ($validated['include_groups'] ?? false),
            'status' => $scheduled ? CekbotBroadcast::STATUS_SCHEDULED : CekbotBroadcast::STATUS_SENDING,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // Snapshot recipients now.
        $conversationIds = $this->recipientQuery(
            (int) $validated['cekbot_session_id'],
            $validated['audience_type'],
            $validated['audience_value'] ?? null,
            $broadcast->include_groups,
        )->pluck('id');

        $broadcast->recipients()->createMany(
            $conversationIds->map(fn ($id) => ['cekbot_conversation_id' => $id, 'status' => 'pending'])->all()
        );

        $broadcast->update(['total_recipients' => $conversationIds->count()]);

        if (! $scheduled) {
            SendCekbotBroadcastJob::dispatch($broadcast->id);

            return back()->with('success', "Broadcast dihantar kepada {$conversationIds->count()} penerima.");
        }

        return back()->with('success', 'Broadcast dijadualkan.');
    }

    public function destroy(CekbotBroadcast $broadcast): RedirectResponse
    {
        if ($broadcast->status === CekbotBroadcast::STATUS_SENDING) {
            return back()->with('error', 'Tidak boleh padam semasa sedang menghantar.');
        }

        $broadcast->delete();

        return back()->with('success', 'Broadcast dipadam.');
    }

    /**
     * @return Builder<CekbotConversation>
     */
    private function recipientQuery(int $sessionId, string $audienceType, ?string $audienceValue, bool $includeGroups): Builder
    {
        return CekbotConversation::query()
            ->where('cekbot_session_id', $sessionId)
            ->whereNull('archived_at')
            ->when(! $includeGroups, fn ($q) => $q->where('is_group', false))
            ->when($audienceType === 'label' && $audienceValue, fn ($q) => $q->whereJsonContains('labels', $audienceValue));
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CekbotBroadcast $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'message' => $b->message,
            'status' => $b->status,
            'audience_type' => $b->audience_type,
            'audience_value' => $b->audience_value,
            'include_groups' => $b->include_groups,
            'scheduled_at' => $b->scheduled_at?->toIso8601String(),
            'total_recipients' => $b->total_recipients,
            'sent_count' => $b->sent_count,
            'failed_count' => $b->failed_count,
            'session' => $b->session?->label,
            'created_ago' => $b->created_at?->diffForHumans(),
        ];
    }
}
