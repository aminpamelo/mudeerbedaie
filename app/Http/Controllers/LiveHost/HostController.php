<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreHostRequest;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HostController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $hosts = User::query()
            ->where('role', 'live_host')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->withCount(['platformAccounts'])
            ->selectSub(
                LiveSession::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('live_host_id', 'users.id'),
                'hosted_sessions_count'
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'status' => $u->status,
                'accounts' => (int) ($u->platform_accounts_count ?? 0),
                'sessions' => (int) ($u->hosted_sessions_count ?? 0),
                'createdAt' => $u->created_at?->toIso8601String(),
                'initials' => $this->initials($u->name),
            ]);

        return Inertia::render('hosts/Index', [
            'hosts' => $hosts,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('hosts/Create', []);
    }

    public function show(User $host): Response
    {
        abort_unless($host->role === 'live_host', 404);

        $host->load(['platformAccounts.platform']);

        $recentSessions = LiveSession::query()
            ->with(['platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->latest('actual_start_at')
            ->take(10)
            ->get()
            ->map(fn (LiveSession $s) => [
                'id' => $s->id,
                'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
                'status' => $s->status,
                'platformAccount' => $s->platformAccount?->name,
                'platformType' => $s->platformAccount?->platform?->slug,
                'scheduledStart' => $s->scheduled_start_at?->toIso8601String(),
                'actualStart' => $s->actual_start_at?->toIso8601String(),
                'actualEnd' => $s->actual_end_at?->toIso8601String(),
            ]);

        $totalSessions = LiveSession::query()
            ->where('live_host_id', $host->id)
            ->count();

        $completedSessions = LiveSession::query()
            ->where('live_host_id', $host->id)
            ->where('status', 'ended')
            ->count();

        return Inertia::render('hosts/Show', [
            'host' => [
                'id' => $host->id,
                'name' => $host->name,
                'email' => $host->email,
                'phone' => $host->phone,
                'status' => $host->status,
                'createdAt' => $host->created_at?->toIso8601String(),
                'initials' => $this->initials($host->name),
            ],
            'platformAccounts' => $host->platformAccounts->map(fn ($pa) => [
                'id' => $pa->id,
                'name' => $pa->name,
                'platform' => $pa->platform?->slug,
                'platformName' => $pa->platform?->name ?? $pa->platform?->display_name,
            ]),
            'recentSessions' => $recentSessions,
            'stats' => [
                'totalSessions' => $totalSessions,
                'completedSessions' => $completedSessions,
                'platformAccounts' => $host->platformAccounts->count(),
            ],
        ]);
    }

    public function store(StoreHostRequest $request): RedirectResponse
    {
        $host = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->string('phone')->toString(),
            'status' => $request->string('status')->toString(),
            'role' => 'live_host',
            'password' => Hash::make(Str::random(40)),
        ]);

        return redirect()
            ->route('livehost.hosts.index')
            ->with('success', "Live host {$host->name} created.");
    }

    private function initials(?string $name): string
    {
        if (! $name) {
            return '??';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(substr(($parts[0] ?? '').($parts[1] ?? ''), 0, 2));
    }
}
