<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAnnouncementRead extends Model
{
    protected $fillable = ['announcement_id', 'student_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(ClassAnnouncement::class, 'announcement_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
