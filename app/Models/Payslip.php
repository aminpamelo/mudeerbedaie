<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'month',
        'year',
        'total_sessions',
        'total_amount',
        'status',
        'generated_at',
        'generated_by',
        'finalized_at',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    // Relationships
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function payslipSessions(): HasMany
    {
        return $this->hasMany(PayslipSession::class);
    }

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(ClassSession::class, 'payslip_sessions', 'payslip_id', 'session_id')
            ->withPivot(['amount', 'included_at'])
            ->withTimestamps();
    }

    // Status methods
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeFinalized(): bool
    {
        return $this->isDraft() && $this->total_sessions > 0;
    }

    public function canBePaid(): bool
    {
        return $this->isFinalized();
    }

    // Status management methods
    public function finalize(): void
    {
        if (! $this->canBeFinalized()) {
            throw new \Exception('Payslip cannot be finalized. Must be in draft status with sessions.');
        }

        $this->update([
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);
    }

    public function markAsPaid(): void
    {
        if (! $this->canBePaid()) {
            throw new \Exception('Payslip cannot be marked as paid. Must be finalized first.');
        }

        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Update all associated sessions' payout status
        $this->sessions()->update(['payout_status' => 'paid']);
    }

    public function revertToDraft(): void
    {
        if (! $this->isFinalized()) {
            throw new \Exception('Can only revert finalized payslips to draft.');
        }

        $this->update([
            'status' => 'draft',
            'finalized_at' => null,
        ]);
    }

    // Session management methods
    public function addSession(ClassSession $session): void
    {
        if (! $this->canBeEdited()) {
            throw new \Exception('Cannot add sessions to non-draft payslip.');
        }

        if ($session->payout_status !== 'unpaid') {
            throw new \Exception('Session is already included in another payslip.');
        }

        // Add session to payslip
        $this->payslipSessions()->create([
            'session_id' => $session->id,
            'amount' => $session->getTeacherAllowanceAmount(),
            'included_at' => now(),
        ]);

        // Update session payout status
        $session->update(['payout_status' => 'included_in_payslip']);

        // Recalculate totals
        $this->recalculateTotals();
    }

    public function removeSession(ClassSession $session): void
    {
        if (! $this->canBeEdited()) {
            throw new \Exception('Cannot remove sessions from non-draft payslip.');
        }

        // Remove session from payslip
        $this->payslipSessions()->where('session_id', $session->id)->delete();

        // Update session payout status
        $session->update(['payout_status' => 'unpaid']);

        // Recalculate totals
        $this->recalculateTotals();
    }

    public function syncSessions(array $sessionIds): void
    {
        if (! $this->canBeEdited()) {
            throw new \Exception('Cannot sync sessions for non-draft payslip.');
        }

        // Get current session IDs
        $currentSessionIds = $this->payslipSessions()->pluck('session_id')->toArray();

        // Sessions to add
        $toAdd = array_diff($sessionIds, $currentSessionIds);
        foreach ($toAdd as $sessionId) {
            $session = ClassSession::find($sessionId);
            if ($session) {
                $this->addSession($session);
            }
        }

        // Sessions to remove
        $toRemove = array_diff($currentSessionIds, $sessionIds);
        foreach ($toRemove as $sessionId) {
            $session = ClassSession::find($sessionId);
            if ($session) {
                $this->removeSession($session);
            }
        }
    }

    public function recalculateTotals(): void
    {
        $totals = $this->payslipSessions()
            ->selectRaw('COUNT(*) as session_count, SUM(amount) as total_amount')
            ->first();

        $this->update([
            'total_sessions' => $totals->session_count ?? 0,
            'total_amount' => $totals->total_amount ?? 0,
        ]);
    }

    // Accessors and utility methods
    public function getFormattedMonthAttribute(): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m', $this->month)->format('F Y');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'badge-yellow',
            'finalized' => 'badge-blue',
            'paid' => 'badge-green',
            default => 'badge-gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'finalized' => 'Finalized',
            'paid' => 'Paid',
            default => ucfirst($this->status),
        };
    }

    // Scopes
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('month', $month);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalized');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    // Static methods for easy creation
    public static function createForTeacher(User $teacher, string $month, User $generatedBy): self
    {
        $year = (int) substr($month, 0, 4);

        return self::create([
            'teacher_id' => $teacher->id,
            'month' => $month,
            'year' => $year,
            'status' => 'draft',
            'generated_at' => now(),
            'generated_by' => $generatedBy->id,
        ]);
    }
}
