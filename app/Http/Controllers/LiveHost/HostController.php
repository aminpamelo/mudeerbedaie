<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Http\Request;
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

    private function initials(?string $name): string
    {
        if (! $name) {
            return '??';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(substr(($parts[0] ?? '').($parts[1] ?? ''), 0, 2));
    }
}
