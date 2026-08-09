<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultAuditLog extends Model
{
    protected $fillable = [
        'credential_id',
        'user_id',
        'action',
        'changes',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function credential(): BelongsTo
    {
        return $this->belongsTo(VaultCredential::class, 'credential_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
