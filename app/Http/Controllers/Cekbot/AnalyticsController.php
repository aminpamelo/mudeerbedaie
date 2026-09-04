<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(): Response
    {
        $now = now();
        $startToday = $now->copy()->startOfDay();
        $start7 = $now->copy()->subDays(6)->startOfDay();

        $out7d = CekbotMessage::query()->where('direction', 'out')->where('created_at', '>=', $start7);

        return Inertia::render('Analytics/Index', [
            'stats' => [
                'numbers' => CekbotSession::query()->count(),
                'connected' => CekbotSession::query()->where('status', 'WORKING')->count(),
                'conversations' => CekbotConversation::query()->whereNull('archived_at')->count(),
                'unread' => (int) CekbotConversation::query()->whereNull('archived_at')->sum('unread_count'),
                'in_today' => CekbotMessage::query()->where('direction', 'in')->where('created_at', '>=', $startToday)->count(),
                'out_today' => CekbotMessage::query()->where('direction', 'out')->where('created_at', '>=', $startToday)->count(),
                'bot_7d' => (clone $out7d)->whereNull('sent_by_user_id')->count(),
                'human_7d' => (clone $out7d)->whereNotNull('sent_by_user_id')->count(),
            ],
            'series' => $this->dailySeries($now),
            'perNumber' => $this->perNumber($start7),
        ]);
    }

    /**
     * 7-day inbound/outbound message series.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dailySeries(\Illuminate\Support\Carbon $now): array
    {
        $series = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $next = $day->copy()->addDay();

            $series[] = [
                'label' => $day->format('D'),
                'in' => CekbotMessage::query()->where('direction', 'in')->whereBetween('created_at', [$day, $next])->count(),
                'out' => CekbotMessage::query()->where('direction', 'out')->whereBetween('created_at', [$day, $next])->count(),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function perNumber(\Illuminate\Support\Carbon $start7): array
    {
        return CekbotSession::query()
            ->orderBy('label')
            ->get()
            ->map(fn (CekbotSession $s) => [
                'label' => $s->label,
                'phone' => $s->phone_number,
                'is_working' => $s->isWorking(),
                'conversations' => CekbotConversation::query()->where('cekbot_session_id', $s->id)->whereNull('archived_at')->count(),
                'in_7d' => CekbotMessage::query()->where('cekbot_session_id', $s->id)->where('direction', 'in')->where('created_at', '>=', $start7)->count(),
                'out_7d' => CekbotMessage::query()->where('cekbot_session_id', $s->id)->where('direction', 'out')->where('created_at', '>=', $start7)->count(),
            ])
            ->all();
    }
}
