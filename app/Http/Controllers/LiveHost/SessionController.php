<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveAnalytics;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $platformAccount = $request->string('platform_account')->toString();
        $host = $request->string('host')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $sessions = LiveSession::query()
            ->with([
                'platformAccount:id,name,platform_id',
                'platformAccount.platform:id,name,display_name,slug',
                'liveHost:id,name,email',
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when(
                $platformAccount !== '',
                fn ($q) => $q->where('platform_account_id', $platformAccount)
            )
            ->when($host !== '', fn ($q) => $q->where('live_host_id', $host))
            ->when($from !== '', fn ($q) => $q->whereDate('scheduled_start_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('scheduled_start_at', '<=', $to))
            ->orderByDesc('scheduled_start_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LiveSession $s) => $this->mapSession($s));

        return Inertia::render('sessions/Index', [
            'sessions' => $sessions,
            'filters' => [
                'status' => $status,
                'platform_account' => $platformAccount,
                'host' => $host,
                'from' => $from,
                'to' => $to,
            ],
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function show(LiveSession $session): Response
    {
        $session->load([
            'platformAccount:id,name,platform_id',
            'platformAccount.platform:id,name,display_name,slug',
            'liveHost:id,name,email',
            'analytics',
            'attachments.uploader:id,name',
        ]);

        return Inertia::render('sessions/Show', [
            'session' => $this->mapSession($session, detailed: true),
            'analytics' => $this->mapAnalytics($session->analytics),
            'attachments' => $session->attachments
                ->map(fn (LiveSessionAttachment $a) => $this->mapAttachment($a))
                ->values(),
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     sessionId: string,
     *     title: ?string,
     *     description: ?string,
     *     status: string,
     *     statusColor: string,
     *     hostId: ?int,
     *     hostName: ?string,
     *     hostEmail: ?string,
     *     platformAccountId: ?int,
     *     platformAccount: ?string,
     *     platformType: ?string,
     *     platformName: ?string,
     *     scheduledStart: ?string,
     *     actualStart: ?string,
     *     actualEnd: ?string,
     *     duration: ?int,
     *     viewers: int,
     *     createdAt?: ?string,
     *     updatedAt?: ?string
     * }
     */
    private function mapSession(LiveSession $s, bool $detailed = false): array
    {
        $base = [
            'id' => $s->id,
            'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
            'title' => $s->title,
            'description' => $s->description,
            'status' => $s->status,
            'statusColor' => $s->status_color,
            'hostId' => $s->live_host_id,
            'hostName' => $s->liveHost?->name,
            'hostEmail' => $s->liveHost?->email,
            'platformAccountId' => $s->platform_account_id,
            'platformAccount' => $s->platformAccount?->name,
            'platformType' => $s->platformAccount?->platform?->slug,
            'platformName' => $s->platformAccount?->platform?->display_name
                ?? $s->platformAccount?->platform?->name,
            'scheduledStart' => $s->scheduled_start_at?->toIso8601String(),
            'actualStart' => $s->actual_start_at?->toIso8601String(),
            'actualEnd' => $s->actual_end_at?->toIso8601String(),
            'duration' => $s->duration,
            'viewers' => 0,
        ];

        if ($detailed) {
            $base['createdAt'] = $s->created_at?->toIso8601String();
            $base['updatedAt'] = $s->updated_at?->toIso8601String();
        }

        return $base;
    }

    /**
     * @return array{
     *     viewersPeak: int,
     *     viewersAvg: int,
     *     totalLikes: int,
     *     totalComments: int,
     *     totalShares: int,
     *     totalEngagement: int,
     *     engagementRate: float,
     *     giftsValue: string,
     *     durationMinutes: int
     * }|null
     */
    private function mapAnalytics(?LiveAnalytics $analytics): ?array
    {
        if (! $analytics) {
            return null;
        }

        return [
            'viewersPeak' => (int) $analytics->viewers_peak,
            'viewersAvg' => (int) $analytics->viewers_avg,
            'totalLikes' => (int) $analytics->total_likes,
            'totalComments' => (int) $analytics->total_comments,
            'totalShares' => (int) $analytics->total_shares,
            'totalEngagement' => $analytics->total_engagement,
            'engagementRate' => $analytics->engagement_rate,
            'giftsValue' => (string) $analytics->gifts_value,
            'durationMinutes' => (int) $analytics->duration_minutes,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     fileName: string,
     *     fileType: string,
     *     fileSize: int,
     *     fileSizeFormatted: string,
     *     fileUrl: string,
     *     description: ?string,
     *     uploaderName: ?string,
     *     isImage: bool,
     *     isVideo: bool,
     *     isPdf: bool,
     *     createdAt: ?string
     * }
     */
    private function mapAttachment(LiveSessionAttachment $a): array
    {
        return [
            'id' => $a->id,
            'fileName' => $a->file_name,
            'fileType' => $a->file_type,
            'fileSize' => (int) $a->file_size,
            'fileSizeFormatted' => $a->file_size_formatted,
            'fileUrl' => $a->file_url,
            'description' => $a->description,
            'uploaderName' => $a->uploader?->name,
            'isImage' => $a->isImage(),
            'isVideo' => $a->isVideo(),
            'isPdf' => $a->isPdf(),
            'createdAt' => $a->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, email: ?string}>
     */
    private function hostOptions(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, platform: ?string}>
     */
    private function platformAccountOptions(): \Illuminate\Support\Collection
    {
        return PlatformAccount::query()
            ->with('platform:id,name,display_name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'platform_id'])
            ->map(fn (PlatformAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'platform' => $a->platform?->display_name ?? $a->platform?->name,
            ]);
    }
}
