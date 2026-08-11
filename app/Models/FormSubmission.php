<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    /** @use HasFactory<\Database\Factories\FormSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'form_id',
        'submitted_by',
        'data',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FormSubmission $submission): void {
            $submission->uuid = $submission->uuid ?: Str::uuid()->toString();
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Resolve a field's stored answer, given the field's builder id.
     */
    public function answerFor(string $fieldId): mixed
    {
        return $this->data[$fieldId] ?? null;
    }
}
