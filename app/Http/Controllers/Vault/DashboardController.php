<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\VaultAuditLog;
use App\Models\VaultCategory;
use App\Models\VaultCredential;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Overview', [
            'totalCredentials' => VaultCredential::query()->count(),
            'byCategory' => VaultCategory::query()
                ->withCount('credentials')
                ->ordered()
                ->get()
                ->map(fn (VaultCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'icon' => $c->icon,
                    'color' => $c->color,
                    'credentials_count' => $c->credentials_count,
                ]),
            'recentActivity' => VaultAuditLog::query()
                ->with(['user:id,name', 'credential:id,name'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (VaultAuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name ?? 'System',
                    'credential' => $log->credential?->name,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
