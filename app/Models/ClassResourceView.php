<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassResourceView extends Model
{
    protected $fillable = [
        'class_resource_id',
        'student_id',
        'first_viewed_at',
        'last_viewed_at',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ClassResource::class, 'class_resource_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
