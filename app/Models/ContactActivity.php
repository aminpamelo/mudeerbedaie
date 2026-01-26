<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactActivity extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'type',
        'title',
        'description',
        'metadata',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public static function log(
        Student $student,
        string $type,
        string $title,
        ?string $description = null,
        array $metadata = [],
        ?int $performedBy = null
    ): self {
        return self::create([
            'student_id' => $student->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
            'performed_by' => $performedBy ?? auth()->id(),
        ]);
    }
}
