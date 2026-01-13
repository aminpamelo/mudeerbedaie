<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentMethodToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'created_by',
        'token',
        'expires_at',
        'last_used_at',
        'usage_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate a new magic link token for a student
     */
    public static function generateForStudent(Student $student, int $expiryDays = 7): self
    {
        // Deactivate any existing active tokens for this student
        self::where('student_id', $student->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return self::create([
            'student_id' => $student->id,
            'created_by' => auth()->id(),
            'token' => Str::random(64),
            'expires_at' => now()->addDays($expiryDays),
            'is_active' => true,
        ]);
    }

    /**
     * Find a valid token by its string value
     */
    public static function findValidToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Check if the token is still valid
     */
    public function isValid(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Record token usage
     * Note: We must explicitly include expires_at to prevent MySQL's
     * auto-update behavior on timestamp columns
     */
    public function recordUsage(): void
    {
        $this->update([
            'last_used_at' => now(),
            'usage_count' => $this->usage_count + 1,
            'expires_at' => $this->expires_at, // Preserve the original expiry date
        ]);
    }

    /**
     * Deactivate the token
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Get the full magic link URL
     */
    public function getMagicLinkUrl(): string
    {
        return route('payment-method.update-guest', ['token' => $this->token]);
    }

    /**
     * Get remaining time until expiry in human-readable format
     */
    public function getExpiresInAttribute(): string
    {
        return $this->expires_at->diffForHumans();
    }

    /**
     * Scope for active tokens
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for valid (active and not expired) tokens
     */
    public function scopeValid($query)
    {
        return $query->active()->where('expires_at', '>', now());
    }
}
