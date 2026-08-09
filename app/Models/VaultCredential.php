<?php

namespace App\Models;

use Database\Factories\VaultCredentialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class VaultCredential extends Model
{
    /** @use HasFactory<VaultCredentialFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'username',
        'password',
        'notes',
        'category_id',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'notes',
    ];

    // ---------------------------------------------------------------- relations

    public function category(): BelongsTo
    {
        return $this->belongsTo(VaultCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(VaultTag::class, 'vault_credential_tag');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(VaultAuditLog::class, 'credential_id');
    }

    // ------------------------------------------------------------------ scopes

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('username', 'like', $like)
                ->orWhere('url', 'like', $like);
        });
    }

    // --------------------------------------------------------------- accessors

    /**
     * Encrypt/decrypt the password transparently.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Encrypt/decrypt notes transparently.
     */
    protected function notes(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}
