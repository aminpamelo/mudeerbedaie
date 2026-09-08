<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotAutoReply;
use App\Models\CekbotBotSetting;
use App\Models\CekbotSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutoReplyController extends Controller
{
    public function index(): Response
    {
        $sessions = CekbotSession::query()
            ->with(['botSetting', 'autoReplies' => fn ($q) => $q->orderBy('priority')->orderBy('id')])
            ->orderBy('label')
            ->get()
            ->map(fn (CekbotSession $session) => [
                'id' => $session->id,
                'label' => $session->label,
                'phone_number' => $session->phone_number,
                'is_working' => $session->isWorking(),
                'settings' => $this->shapeSettings($session->botSetting),
                'rules' => $session->autoReplies->map(fn (CekbotAutoReply $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'match_type' => $r->match_type,
                    'keywords' => $r->keywords,
                    'reply_body' => $r->reply_body,
                    'is_active' => $r->is_active,
                    'priority' => $r->priority,
                ])->values(),
            ]);

        return Inertia::render('AutoReply/Index', [
            'sessions' => $sessions,
            'aiAvailable' => filled(config('openai.api_key')),
        ]);
    }

    public function updateSettings(Request $request, CekbotSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'bot_enabled' => 'boolean',
            'test_mode' => 'boolean',
            'test_numbers' => 'nullable|array|max:50',
            'test_numbers.*' => 'nullable|string|max:30',
            'reply_to_groups' => 'boolean',
            'checks_enabled' => 'boolean',
            'welcome_message' => 'nullable|string|max:4096',
            'default_reply' => 'nullable|string|max:4096',
            'away_message' => 'nullable|string|max:4096',
            'business_hours' => 'nullable|array',
            'business_hours.enabled' => 'boolean',
            'business_hours.start' => 'nullable|string|max:5',
            'business_hours.end' => 'nullable|string|max:5',
            'business_hours.days' => 'nullable|array',
            'business_hours.days.*' => 'integer|min:1|max:7',
            'ai_enabled' => 'boolean',
            'ai_system_prompt' => 'nullable|string|max:4096',
        ]);

        if (array_key_exists('test_numbers', $validated)) {
            $validated['test_numbers'] = collect($validated['test_numbers'] ?? [])
                ->map(fn ($number) => trim((string) $number))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        CekbotBotSetting::updateOrCreate(
            ['cekbot_session_id' => $session->id],
            $validated,
        );

        return back()->with('success', 'Tetapan bot dikemas kini.');
    }

    public function storeRule(Request $request, CekbotSession $session): RedirectResponse
    {
        $validated = $this->validateRule($request);

        $session->autoReplies()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Peraturan auto-reply ditambah.');
    }

    public function updateRule(Request $request, CekbotAutoReply $rule): RedirectResponse
    {
        $rule->update($this->validateRule($request));

        return back()->with('success', 'Peraturan dikemas kini.');
    }

    public function destroyRule(CekbotAutoReply $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Peraturan dipadam.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'match_type' => 'required|in:contains,exact,starts,regex',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'required|string|max:255',
            'reply_body' => 'required|string|max:4096',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:0|max:9999',
        ]);

        $validated['keywords'] = array_values(array_filter(array_map('trim', $validated['keywords'])));
        $validated['priority'] = $validated['priority'] ?? 100;

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeSettings(?CekbotBotSetting $settings): array
    {
        return [
            'bot_enabled' => (bool) $settings?->bot_enabled,
            'test_mode' => (bool) $settings?->test_mode,
            'test_numbers' => $settings?->test_numbers ?? [],
            'reply_to_groups' => (bool) $settings?->reply_to_groups,
            'checks_enabled' => (bool) $settings?->checks_enabled,
            'welcome_message' => $settings?->welcome_message,
            'default_reply' => $settings?->default_reply,
            'away_message' => $settings?->away_message,
            'business_hours' => $settings?->business_hours ?? ['enabled' => false, 'start' => '09:00', 'end' => '18:00', 'days' => [1, 2, 3, 4, 5]],
            'ai_enabled' => (bool) $settings?->ai_enabled,
            'ai_system_prompt' => $settings?->ai_system_prompt,
        ];
    }
}
