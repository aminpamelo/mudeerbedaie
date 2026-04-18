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
