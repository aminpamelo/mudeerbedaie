<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\VaultAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = VaultAuditLog::query()
            ->with(['user:id,name', 'credential:id,name'])
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', $action))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (VaultAuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->name ?? 'System',
                'credential' => $log->credential?->name,
                'changes' => $log->changes,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('AuditLog', [
            'logs' => $logs,
            'filters' => $request->only(['action']),
        ]);
    }
}
