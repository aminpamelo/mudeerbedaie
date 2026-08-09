<?php

namespace App\Services;

use App\Models\VaultAuditLog;
use App\Models\VaultCredential;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class VaultAuditService
{
    /**
     * Record an audit event, redacting secret fields so plain-text passwords
     * and notes never land in the log table.
     */
    public function log(string $action, ?VaultCredential $credential = null, ?array $changes = null): VaultAuditLog
    {
        if ($changes) {
            foreach (['password', 'notes'] as $secret) {
                if (isset($changes['old'][$secret])) {
                    $changes['old'][$secret] = '***';
                }
                if (isset($changes['new'][$secret])) {
                    $changes['new'][$secret] = '***';
                }
            }
        }

        return VaultAuditLog::create([
            'credential_id' => $credential?->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes,
            'ip_address' => Request::ip(),
        ]);
    }
}
