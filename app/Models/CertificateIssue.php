<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'student_id',
        'enrollment_id',
        'class_id',
        'certificate_number',
        'issue_date',
        'issued_by',
        'file_path',
        'data_snapshot',
        'status',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'data_snapshot' => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($certificateIssue) {
            if (empty($certificateIssue->certificate_number)) {
                $certificateIssue->certificate_number = static::generateCertificateNumber();
            }

            if (empty($certificateIssue->issue_date)) {
                $certificateIssue->issue_date = now();
            }
        });

        static::created(function ($certificateIssue) {
            $certificateIssue->logAction('issued', auth()->user());
        });
    }

    // Relationships

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CertificateLog::class);
    }

    // Status methods

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function canBeRevoked(): bool
    {
        return $this->isIssued();
    }

    public function canBeReinstated(): bool
    {
        return $this->isRevoked();
    }

    public function revoke(string $reason, User $user): void
    {
        if (! $this->canBeRevoked()) {
            throw new \Exception('Certificate cannot be revoked.');
        }

        $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $user->id,
            'revocation_reason' => $reason,
        ]);

        $this->logAction('revoked', $user);
    }

    public function reinstate(User $user): void
    {
        if (! $this->canBeReinstated()) {
            throw new \Exception('Certificate cannot be reinstated.');
        }

        $this->update([
            'status' => 'issued',
            'revoked_at' => null,
            'revoked_by' => null,
            'revocation_reason' => null,
        ]);

        $this->logAction('reinstated', $user);
    }

    // Certificate number generation

    public static function generateCertificateNumber(): string
    {
        $year = now()->year;
        $lastNumber = static::whereYear('created_at', $year)
            ->latest('id')
            ->value('certificate_number');

        if ($lastNumber) {
            // Extract number from format CERT-2025-0001
            $parts = explode('-', $lastNumber);
            $number = (int) end($parts) + 1;
        } else {
            $number = 1;
        }

        return sprintf('CERT-%d-%04d', $year, $number);
    }

    // Verification

    public function getVerificationUrl(): string
    {
        return route('certificates.verify', $this->certificate_number);
    }

    public function getVerificationQrCode(): string
    {
        // QR code will be generated using SimpleSoftwareIO/simple-qrcode
        return \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)
            ->generate($this->getVerificationUrl());
    }

    // Logging

    public function logAction(string $action, ?User $user = null): void
    {
        $this->logs()->create([
            'action' => $action,
            'user_id' => $user?->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    // Data snapshot helpers

    public function getStudentName(): string
    {
        return $this->data_snapshot['student_name'] ?? $this->student?->full_name ?? 'Unknown';
    }

    public function getCourseName(): string
    {
        return $this->data_snapshot['course_name'] ?? 'Unknown Course';
    }

    public function getClassName(): string
    {
        return $this->data_snapshot['class_name'] ?? 'Unknown Class';
    }

    public function getCertificateName(): string
    {
        return $this->data_snapshot['certificate_name'] ?? $this->certificate?->name ?? 'Certificate';
    }

    // File management

    public function hasFile(): bool
    {
        return ! empty($this->file_path) && \Storage::disk('public')->exists($this->file_path);
    }

    public function getFileUrl(): ?string
    {
        if (! $this->hasFile()) {
            return null;
        }

        return \Storage::disk('public')->url($this->file_path);
    }

    public function getDownloadFilename(): string
    {
        $studentName = preg_replace('/[^a-zA-Z0-9\s]/', '', $this->getStudentName());
        $studentName = str_replace(' ', '_', trim($studentName));

        $phone = $this->student?->phone_number;
        $phone = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;

        $parts = array_filter([$studentName, $phone, $this->certificate_number]);

        return implode('_', $parts).'.pdf';
    }

    public function getDownloadUrl(): string
    {
        return route('certificates.download', $this->id);
    }

    public function deleteFile(): void
    {
        if ($this->hasFile()) {
            \Storage::disk('public')->delete($this->file_path);
        }
    }

    // Scopes

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeRevoked($query)
    {
        return $query->where('status', 'revoked');
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->whereHas('enrollment', fn ($q) => $q->where('course_id', $courseId));
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeIssuedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('issue_date', [$startDate, $endDate]);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('issue_date', now()->year);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereYear('issue_date', now()->year)
            ->whereMonth('issue_date', now()->month);
    }

    // Badge helper

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'issued' => 'badge-green',
            'revoked' => 'badge-red',
            default => 'badge-gray',
        };
    }

    public function getFormattedIssueDateAttribute(): string
    {
        return $this->issue_date->format('M d, Y');
    }
}
